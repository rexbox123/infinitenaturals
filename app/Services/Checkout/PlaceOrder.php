<?php

namespace App\Services\Checkout;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a cart into an order. Runs in one transaction that locks the variant
 * rows, re-checks stock and prices, creates the order and deducts stock, so
 * concurrent customers can never oversell limited stock.
 */
class PlaceOrder
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PriceCalculator $calculator,
    ) {}

    /**
     * @param  array{email: string, phone?: ?string, first_name: string, last_name: string, address_line1: string,
     *     address_line2?: ?string, city: string, state: string, postal_code: string, shipping_method_id: int,
     *     payment_method: string, customer_note?: ?string, expected_total?: ?int}  $data
     *
     * @throws CheckoutException
     */
    public function handle(Cart $cart, array $data, ?int $userId = null, ?string $ip = null): Order
    {
        $cart->loadMissing('items');
        if ($cart->items->isEmpty()) {
            throw new CheckoutException('Your cart is empty.');
        }

        $quantities = $cart->items->mapWithKeys(fn ($i) => [$i->product_variant_id => $i->quantity])->all();
        $email = strtolower(trim($data['email']));

        try {
            $order = DB::transaction(function () use ($cart, $data, $quantities, $email, $userId, $ip) {
                $variants = $this->inventory->lockAndCheck($quantities);

                $shipping = ShippingMethod::query()->active()->find($data['shipping_method_id'])
                    ?? throw new CheckoutException('Please choose an available shipping option.');

                $discount = null;
                if ($cart->discount_code_id) {
                    $discount = DiscountCode::query()->lockForUpdate()->find($cart->discount_code_id);
                }

                $lines = collect($quantities)->map(fn ($qty, $id) => ['variant' => $variants[$id], 'quantity' => $qty]);
                $totals = $this->calculator->calculate($lines, $discount, $shipping, $data['state'], $email);

                if ($discount && $totals->discountMessage) {
                    throw new CheckoutException("Discount code {$discount->code}: {$totals->discountMessage}");
                }

                if (isset($data['expected_total']) && (int) $data['expected_total'] !== $totals->total()) {
                    throw new CheckoutException('Prices or availability changed while you were checking out. Please review the updated total and place your order again.');
                }

                $isCod = $data['payment_method'] === 'cod';

                $order = Order::query()->create([
                    'number' => 'TMP-'.Str::random(20),
                    'access_token' => Str::random(48),
                    'user_id' => $userId,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'address_line1' => $data['address_line1'],
                    'address_line2' => $data['address_line2'] ?? null,
                    'city' => $data['city'],
                    'state' => strtoupper($data['state']),
                    'postal_code' => $data['postal_code'],
                    'country' => 'US',
                    'shipping_method_id' => $shipping->id,
                    'shipping_method_name' => $shipping->name,
                    'discount_code_id' => $discount?->id,
                    'discount_code' => $discount?->code,
                    'subtotal' => $totals->subtotal,
                    'discount_total' => $totals->discount,
                    'shipping_total' => $totals->shipping ?? 0,
                    'tax_total' => $totals->tax,
                    'tax_rate' => $totals->taxRate,
                    'total' => $totals->total(),
                    'currency' => config('store.currency'),
                    'payment_method' => $isCod ? 'cod' : 'stripe',
                    'payment_status' => PaymentStatus::Pending,
                    'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                    'payment_expires_at' => $isCod ? null : now()->addMinutes(max(31, config('store.payment_timeout_minutes'))),
                    'customer_note' => $data['customer_note'] ?? null,
                    'ip_address' => $ip,
                ]);
                $order->forceFill(['number' => config('store.order_number_prefix').(100000 + $order->id)])->save();

                foreach ($quantities as $variantId => $qty) {
                    $variant = $variants[$variantId];
                    $order->items()->create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'variant_name' => $variant->name,
                        'sku' => $variant->sku,
                        'unit_price' => $variant->currentPrice(),
                        'quantity' => $qty,
                        'line_total' => $variant->currentPrice() * $qty,
                    ]);
                }

                $order->load('items');
                $this->inventory->deductForOrder($order);

                if ($discount) {
                    $discount->increment('times_used');
                }

                $order->addEvent('status', 'Order placed'.($isCod ? ' (cash on delivery).' : '; awaiting online payment.'), true);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            throw new CheckoutException($e->getMessage(), previous: $e);
        }

        // The order now holds the stock; empty the cart.
        $cart->items()->delete();
        $cart->update(['discount_code_id' => null]);

        return $order;
    }
}
