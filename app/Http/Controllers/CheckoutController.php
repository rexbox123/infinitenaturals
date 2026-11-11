<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\TaxRate;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutException;
use App\Services\Checkout\PlaceOrder;
use App\Services\Checkout\PriceCalculator;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\StripeCheckout;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    /** @return array<string, string> code => label */
    public static function availablePaymentMethods(): array
    {
        $methods = [];
        if (StripeCheckout::isAvailable()) {
            $methods['stripe'] = 'Pay securely online';
        }
        if (setting('payments.cod_enabled', false)) {
            $methods['cod'] = 'Cash on delivery';
        }

        return $methods;
    }

    public function show(Request $request, PriceCalculator $calculator)
    {
        $notices = $this->carts->reconcile();
        $cart = $this->carts->current();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.show')->with('notices', $notices);
        }

        $lines = $cart->items->map(fn ($i) => ['variant' => $i->variant, 'quantity' => $i->quantity]);
        $totals = $calculator->calculate($lines, $cart->discountCode, null, null, $request->user()?->email);

        $user = $request->user();
        $lastOrder = $user?->orders()->latest('id')->first();
        $prefill = [
            'email' => $user?->email,
            'phone' => $user?->phone ?? $lastOrder?->phone,
            'first_name' => $lastOrder?->first_name ?? ($user ? strtok($user->name, ' ') : null),
            'last_name' => $lastOrder?->last_name,
            'address_line1' => $lastOrder?->address_line1,
            'address_line2' => $lastOrder?->address_line2,
            'city' => $lastOrder?->city,
            'state' => $lastOrder?->state,
            'postal_code' => $lastOrder?->postal_code,
        ];

        return view('store.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'notices' => $notices,
            'prefill' => $prefill,
            'shippingMethods' => ShippingMethod::query()->active()->get(),
            'taxRates' => TaxRate::query()->where('is_active', true)->get()
                ->mapWithKeys(fn ($r) => [$r->state => ['rate' => (float) $r->rate, 'shipping' => $r->applies_to_shipping]]),
            'paymentMethods' => self::availablePaymentMethods(),
            'states' => config('store.shipping_states'),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder, StripeCheckout $stripe, OrderPayments $payments)
    {
        $cart = $this->carts->current();
        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.show');
        }

        try {
            $order = $placeOrder->handle($cart, $request->validated(), $request->user()?->id, $request->ip());
        } catch (CheckoutException $e) {
            return redirect()->route('checkout.show')->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        if ($order->isCashOnDelivery()) {
            $payments->sendConfirmation($order);

            return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token])
                ->with('status', 'Thank you! Your order has been placed.');
        }

        try {
            return redirect()->away($stripe->start($order));
        } catch (PaymentGatewayException $e) {
            // Could not create a payment page: release the stock and give the cart back.
            $payments->markCancelled($order, 'Payment page could not be created: '.$e->getMessage());
            $this->restoreCart($order);

            return redirect()->route('checkout.show')->withErrors(['checkout' => $e->getMessage()])->withInput();
        }
    }

    /** Put an unpaid order's items back in the cart so the customer can try again. */
    public static function restoreCart(Order $order): void
    {
        $carts = app(CartService::class);
        foreach ($order->items as $item) {
            if ($item->variant) {
                try {
                    $carts->add($item->variant, $item->quantity);
                } catch (\Illuminate\Validation\ValidationException) {
                    // Item no longer available in that quantity; the cart page will explain.
                }
            }
        }
    }
}
