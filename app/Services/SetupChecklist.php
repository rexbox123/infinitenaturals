<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Payments\StripeCheckout;

/**
 * Things the owner still has to provide or decide before going live.
 * Shown on the admin dashboard until resolved.
 */
class SetupChecklist
{
    /** @return list<array{label: string, detail: string, url: ?string}> */
    public function items(): array
    {
        $items = [];

        if (! StripeCheckout::isConfigured()) {
            $items[] = ['label' => 'Connect Stripe', 'detail' => 'Set STRIPE_KEY, STRIPE_SECRET and STRIPE_WEBHOOK_SECRET in the server environment (see docs/payments.md).', 'url' => null];
        } elseif (! config('services.stripe.webhook_secret')) {
            $items[] = ['label' => 'Add the Stripe webhook secret', 'detail' => 'Without STRIPE_WEBHOOK_SECRET, delayed payments, expirations and refunds will not update orders.', 'url' => null];
        } elseif (StripeCheckout::mode() === 'test' && app()->environment('production')) {
            $items[] = ['label' => 'Stripe is in test mode', 'detail' => 'Replace the test keys with live keys when you are ready to accept real payments.', 'url' => null];
        }

        if (! ShippingMethod::query()->where('is_active', true)->exists()) {
            $items[] = ['label' => 'Set shipping rates', 'detail' => 'No shipping method is active, so checkout is closed. Enter your USPS rates and activate at least one method.', 'url' => route('admin.shipping-methods.index')];
        }

        if (! TaxRate::query()->exists()) {
            $items[] = ['label' => 'Confirm sales tax', 'detail' => 'No sales tax rates are configured. Ask your accountant which states you must collect in and add their rates.', 'url' => route('admin.tax-rates.index')];
        }

        $noImages = Product::query()->where('status', 'active')->doesntHave('images')->count();
        if ($noImages > 0) {
            $items[] = ['label' => "Upload product photos ({$noImages})", 'detail' => 'Some active products show a placeholder image. Upload photos or run `php artisan store:import-images`.', 'url' => route('admin.products.index', ['missing' => 'images'])];
        }

        $noStock = Product::query()->where('status', 'active')
            ->whereHas('variants', fn ($q) => $q->where('track_inventory', true)->where('stock_quantity', 0))->count();
        if ($noStock > 0) {
            $items[] = ['label' => "Enter stock levels ({$noStock} products at 0)", 'detail' => 'Products with zero stock show as sold out.', 'url' => route('admin.inventory.index')];
        }

        $missingSku = Product::query()->whereHas('variants', fn ($q) => $q->whereNull('sku'))->count();
        if ($missingSku > 0) {
            $items[] = ['label' => "Add SKUs ({$missingSku} products)", 'detail' => 'The old site had no SKUs. Add your own codes to make stock-keeping easier.', 'url' => route('admin.products.index')];
        }

        $draftPages = Page::query()->where('is_system', true)->where('is_published', false)->pluck('title');
        if ($draftPages->isNotEmpty()) {
            $items[] = ['label' => 'Review unpublished pages', 'detail' => $draftPages->implode(', ').' need your review before publishing.', 'url' => route('admin.pages.index')];
        }

        if (User::query()->where('role', 'admin')->where('email', 'like', '%@example.%')->exists()) {
            $items[] = ['label' => 'Replace the demo admin account', 'detail' => 'An admin with an example.com email exists. Create your own admin and delete the demo one.', 'url' => route('admin.staff.index')];
        }

        return $items;
    }
}
