@php($s = fn ($key) => $settings->get($key))
<x-admin-layout title="Settings">
    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid max-w-5xl gap-6 lg:grid-cols-2">
        @csrf @method('put')
        <x-admin.card title="Store details">
            <div class="grid gap-4">
                <x-admin.field name="store_name" label="Store name" :value="$s('store.name')" required />
                <x-admin.field name="store_tagline" label="Tagline" :value="$s('store.tagline')" />
                <x-admin.field name="store_footer_blurb" label="Footer text" type="textarea" rows="2" :value="$s('store.footer_blurb')" />
                <div>
                    <label class="label" for="logo">Logo</label>
                    @if ($s('store.logo_path'))<img src="{{ Storage::disk('public')->url($s('store.logo_path')) }}" alt="Logo" class="mb-2 h-12"><x-admin.checkbox name="remove_logo" label="Remove logo (use text wordmark)" class="mb-2" />@endif
                    <input id="logo" type="file" name="logo" accept="image/*" class="text-sm">
                    <p class="mt-1 text-xs text-stone-500">PNG or SVG with a transparent background, about 400×120 px.</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Contact & notifications">
            <div class="grid gap-4">
                <x-admin.field name="store_email" label="Public email" type="email" :value="$s('store.email')" required help="Shown on the site; contact-form messages are sent here." />
                <x-admin.field name="store_phone" label="Phone" :value="$s('store.phone')" />
                <x-admin.field name="store_address" label="Address" :value="$s('store.address')" />
                <x-admin.field name="store_order_notification_email" label="Send new-order alerts to" type="email" :value="$s('store.order_notification_email')" />
                <x-admin.field name="store_social_facebook" label="Facebook URL" :value="$s('store.social_facebook')" />
                <x-admin.field name="store_social_instagram" label="Instagram URL" :value="$s('store.social_instagram')" />
            </div>
        </x-admin.card>

        <x-admin.card title="Payments">
            <div class="space-y-4 text-sm">
                <div class="rounded-lg border p-4 {{ $stripe['configured'] ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' }}">
                    <p class="font-semibold">Stripe:
                        @if ($stripe['configured']) connected ({{ strtoupper($stripe['mode']) }} mode) @else not configured @endif
                    </p>
                    <p class="mt-1">Webhook secret: {{ $stripe['webhook'] ? 'set' : 'missing' }}. Endpoint to register in Stripe: <code class="break-all">{{ $stripe['webhook_url'] }}</code></p>
                    <p class="mt-1 text-xs text-stone-600">API keys are secrets and are set in the server environment (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET), never in this form. Payment methods shown at checkout (cards, Apple Pay, Google Pay, Link, etc.) are switched on in the Stripe Dashboard → Settings → Payment methods.</p>
                </div>
                <x-admin.checkbox name="payments_stripe_enabled" label="Accept online payments with Stripe" :checked="(bool) $s('payments.stripe_enabled')" />
                <x-admin.checkbox name="payments_cod_enabled" label="Offer cash on delivery" help="Only enable if your business and carrier support collecting payment on delivery." :checked="(bool) $s('payments.cod_enabled')" />
                <x-admin.field name="payments_cod_instructions" label="Cash on delivery instructions" type="textarea" rows="2" :value="$s('payments.cod_instructions')" />
            </div>
        </x-admin.card>

        <x-admin.card title="Checkout & inventory">
            <div class="space-y-4">
                <x-admin.checkbox name="checkout_require_terms" label="Require customers to accept the terms at checkout" :checked="(bool) $s('checkout.require_terms')" />
                <x-admin.field name="inventory_low_stock_threshold" label="Low-stock alert level" type="number" min="0" :value="$s('inventory.low_stock_threshold')" required />
                <p class="text-sm text-stone-600">Shipping rates: <a href="{{ route('admin.shipping-methods.index') }}" class="text-umber-700 underline">Shipping</a> · Tax rates: <a href="{{ route('admin.tax-rates.index') }}" class="text-umber-700 underline">Sales tax</a></p>
            </div>
        </x-admin.card>

        <div class="lg:col-span-2"><button class="btn-primary btn-sm">Save settings</button></div>
    </form>
</x-admin-layout>
