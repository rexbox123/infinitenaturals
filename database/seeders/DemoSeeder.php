<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT ONLY. Adds demo logins, stock, placeholder shipping rates,
 * a discount code and sample orders so every screen has something to show.
 * Nothing here reflects real business data. Never run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder skipped in production.');

            return;
        }

        $admin = User::query()->firstOrNew(['email' => 'admin@example.com']);
        $admin->fill(['name' => 'Demo Admin', 'password' => 'password'])->forceFill(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()])->save();

        $staff = User::query()->firstOrNew(['email' => 'staff@example.com']);
        $staff->fill(['name' => 'Demo Fulfillment', 'password' => 'password'])->forceFill(['role' => 'staff', 'is_active' => true, 'email_verified_at' => now()])->save();

        $customer = User::query()->firstOrNew(['email' => 'customer@example.com']);
        $customer->fill(['name' => 'Demo Customer', 'password' => 'password', 'phone' => '555-010-0000'])->forceFill(['email_verified_at' => now()])->save();

        // Demo stock levels (one low, so the low-stock widgets show something).
        foreach (ProductVariant::query()->with('product')->get() as $variant) {
            if ($variant->stock_quantity === 0) {
                $level = $variant->product->slug === 'heart-soap-rose' ? 3 : 25;
                $inventory->setLevel($variant, $level, 'initial', null, 'Demo stock');
            }
        }

        ShippingMethod::query()->where('name', 'USPS Standard')->where('rate', 0)
            ->update(['rate' => 595, 'is_active' => true, 'description' => 'DEMO RATE — replace before launch. 3–10 business days.']);
        ShippingMethod::query()->where('name', 'USPS Priority Mail')->where('rate', 0)
            ->update(['rate' => 1095, 'is_active' => true, 'description' => 'DEMO RATE — replace before launch.']);

        DiscountCode::query()->firstOrCreate(['code' => 'DEMO10'], [
            'description' => 'Demo code — 10% off',
            'type' => DiscountType::Percent,
            'value' => 10,
            'is_active' => true,
        ]);

        if (Order::query()->exists()) {
            return;
        }

        $samples = [
            [PaymentStatus::Paid, FulfillmentStatus::Shipped, 3],
            [PaymentStatus::Paid, FulfillmentStatus::Unfulfilled, 1],
            [PaymentStatus::Paid, FulfillmentStatus::Delivered, 9],
            [PaymentStatus::Cancelled, FulfillmentStatus::Cancelled, 2],
        ];
        $variants = ProductVariant::query()->with('product')->get();
        foreach ($samples as $i => [$payment, $fulfillment, $daysAgo]) {
            $picked = $variants->random(2);
            $subtotal = $picked->sum('price');
            $order = Order::query()->create([
                'number' => 'TMP-'.Str::random(20),
                'access_token' => Str::random(48),
                'user_id' => $i === 0 ? $customer->id : null,
                'email' => $i === 0 ? $customer->email : "guest{$i}@example.com",
                'phone' => '555-010-000'.$i,
                'first_name' => $i === 0 ? 'Demo' : 'Guest',
                'last_name' => $i === 0 ? 'Customer' : "Buyer {$i}",
                'address_line1' => '123 Demo Street',
                'city' => 'Ocala',
                'state' => 'FL',
                'postal_code' => '34480',
                'shipping_method_name' => 'USPS Standard',
                'subtotal' => $subtotal,
                'shipping_total' => $subtotal >= 5000 ? 0 : 595,
                'total' => $subtotal + ($subtotal >= 5000 ? 0 : 595),
                'payment_method' => 'stripe',
                'payment_status' => $payment,
                'fulfillment_status' => $fulfillment,
                'paid_at' => $payment === PaymentStatus::Paid ? now()->subDays($daysAgo) : null,
                'shipped_at' => in_array($fulfillment, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered]) ? now()->subDays($daysAgo - 1) : null,
                'tracking_carrier' => $fulfillment === FulfillmentStatus::Shipped ? 'usps' : null,
                'tracking_number' => $fulfillment === FulfillmentStatus::Shipped ? '9400100000000000000000' : null,
                'stock_released_at' => $payment === PaymentStatus::Cancelled ? now() : null,
                'confirmation_sent_at' => now(),
                'created_at' => now()->subDays($daysAgo),
            ]);
            $order->forceFill(['number' => config('store.order_number_prefix').(100000 + $order->id)])->save();
            foreach ($picked as $v) {
                $order->items()->create([
                    'product_id' => $v->product_id, 'product_variant_id' => $v->id,
                    'product_name' => $v->product->name, 'variant_name' => $v->name, 'sku' => $v->sku,
                    'unit_price' => $v->price, 'quantity' => 1, 'line_total' => $v->price,
                ]);
            }
            $order->addEvent('status', 'Demo order created by DemoSeeder.');
        }
    }
}
