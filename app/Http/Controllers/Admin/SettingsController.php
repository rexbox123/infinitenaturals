<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeCheckout;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit(Settings $settings)
    {
        return view('admin.settings', [
            'settings' => $settings,
            'stripe' => [
                'configured' => StripeCheckout::isConfigured(),
                'mode' => StripeCheckout::mode(),
                'webhook' => filled(config('services.stripe.webhook_secret')),
                'webhook_url' => route('webhooks.stripe'),
            ],
        ]);
    }

    public function update(Request $request, Settings $settings)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'store_tagline' => ['nullable', 'string', 'max:255'],
            'store_footer_blurb' => ['nullable', 'string', 'max:500'],
            'store_email' => ['required', 'email', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:40'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_order_notification_email' => ['nullable', 'email', 'max:255'],
            'store_social_facebook' => ['nullable', 'url:https', 'max:255'],
            'store_social_instagram' => ['nullable', 'url:https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
            'logo_light' => ['nullable', 'image', 'mimes:png,webp,svg', 'max:2048'],
            'remove_logo_light' => ['boolean'],
            'payments_cod_instructions' => ['nullable', 'string', 'max:1000'],
            'inventory_low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $values = [
            'store.name' => $data['store_name'],
            'store.tagline' => $data['store_tagline'] ?? null,
            'store.footer_blurb' => $data['store_footer_blurb'] ?? null,
            'store.email' => $data['store_email'],
            'store.phone' => $data['store_phone'] ?? null,
            'store.address' => $data['store_address'] ?? null,
            'store.order_notification_email' => $data['store_order_notification_email'] ?? null,
            'store.social_facebook' => $data['store_social_facebook'] ?? null,
            'store.social_instagram' => $data['store_social_instagram'] ?? null,
            'payments.stripe_enabled' => $request->boolean('payments_stripe_enabled'),
            'payments.cod_enabled' => $request->boolean('payments_cod_enabled'),
            'payments.cod_instructions' => $data['payments_cod_instructions'] ?? null,
            'checkout.require_terms' => $request->boolean('checkout_require_terms'),
            'inventory.low_stock_threshold' => (int) $data['inventory_low_stock_threshold'],
        ];

        foreach (['logo' => 'store.logo_path', 'logo_light' => 'store.logo_light_path'] as $field => $key) {
            if ($request->boolean("remove_{$field}") && ($old = $settings->get($key))) {
                Storage::disk('public')->delete($old);
                $values[$key] = null;
            }
            if ($request->hasFile($field)) {
                $values[$key] = $request->file($field)->store('branding', 'public');
            }
        }

        $settings->setMany($values);

        return back()->with('status', 'Settings saved.');
    }
}
