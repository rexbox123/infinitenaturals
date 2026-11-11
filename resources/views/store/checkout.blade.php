@php
    $methods = $shippingMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'rate' => $m->rate, 'free_over' => $m->free_over])->values();
    $selectedShipping = (int) old('shipping_method_id', $shippingMethods->first()?->id);
    $selectedState = old('state', $prefill['state'] ?? '');
    $canCheckout = $shippingMethods->isNotEmpty() && count($paymentMethods) > 0;
    $field = fn ($name) => old($name, $prefill[$name] ?? '');
@endphp
<x-store-layout title="Checkout" noindex>
    <div class="container-store py-10"
         x-data="checkout({ subtotal: {{ $totals->subtotal }}, discount: {{ $totals->discount }}, freeShip: {{ $totals->freeShippingFromDiscount ? 'true' : 'false' }},
                 methods: {{ Js::from($methods) }}, taxes: {{ Js::from($taxRates) }}, shipping: {{ $selectedShipping ?: 'null' }}, state: {{ Js::from($selectedState) }} })">
        <h1 class="font-serif text-4xl">Checkout</h1>
        <p class="mt-2 text-sm text-stone-600">
            @guest Have an account? <a href="{{ route('login') }}" class="link">Sign in</a> for faster checkout — or continue as a guest. @endguest
        </p>

        @foreach ($notices as $notice)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">{{ $notice }}</div>
        @endforeach
        @error('checkout')
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror
        @if ($errors->any() && ! $errors->has('checkout'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">Please correct the highlighted fields below.</div>
        @endif

        @unless ($canCheckout)
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900" role="alert">
                Online checkout is temporarily unavailable. Please contact us at {{ setting('store.phone') }} or <a class="link" href="mailto:{{ setting('store.email') }}">{{ setting('store.email') }}</a> to place your order.
            </div>
        @endunless

        <form method="post" action="{{ route('checkout.store') }}" class="mt-8 grid gap-10 lg:grid-cols-[1fr_400px]" @submit="submitting = true" novalidate>
            @csrf
            <input type="hidden" name="expected_total" :value="total">
            <div class="space-y-10">
                <section aria-labelledby="contact-heading">
                    <h2 id="contact-heading" class="font-serif text-2xl">Contact</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="label">Email</label>
                            <input id="email" type="email" name="email" value="{{ $field('email') }}" required autocomplete="email" class="input" aria-describedby="email-help">
                            <p id="email-help" class="mt-1 text-xs text-stone-500">Your receipt and order updates go here.</p>
                            @error('email')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="phone" class="label">Phone</label>
                            <input id="phone" type="tel" name="phone" value="{{ $field('phone') }}" required autocomplete="tel" class="input">
                            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section aria-labelledby="ship-heading">
                    <h2 id="ship-heading" class="font-serif text-2xl">Delivery address</h2>
                    <p class="mt-1 text-sm text-stone-600">We ship to all 50 U.S. states. We do not ship to APO/FPO addresses.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="label">First name</label>
                            <input id="first_name" name="first_name" value="{{ $field('first_name') }}" required autocomplete="given-name" class="input">
                            @error('first_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last_name" class="label">Last name</label>
                            <input id="last_name" name="last_name" value="{{ $field('last_name') }}" required autocomplete="family-name" class="input">
                            @error('last_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address_line1" class="label">Street address</label>
                            <input id="address_line1" name="address_line1" value="{{ $field('address_line1') }}" required autocomplete="address-line1" class="input">
                            @error('address_line1')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address_line2" class="label">Apartment, suite, etc. <span class="font-normal text-stone-500">(optional)</span></label>
                            <input id="address_line2" name="address_line2" value="{{ $field('address_line2') }}" autocomplete="address-line2" class="input">
                        </div>
                        <div>
                            <label for="city" class="label">City</label>
                            <input id="city" name="city" value="{{ $field('city') }}" required autocomplete="address-level2" class="input">
                            @error('city')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="state" class="label">State</label>
                                <select id="state" name="state" required autocomplete="address-level1" class="input" x-model="state">
                                    <option value="">Select…</option>
                                    @foreach ($states as $code => $name)
                                        <option value="{{ $code }}" @selected($selectedState === $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('state')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="postal_code" class="label">ZIP code</label>
                                <input id="postal_code" name="postal_code" value="{{ $field('postal_code') }}" required autocomplete="postal-code" inputmode="numeric" class="input">
                                @error('postal_code')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="shipping-heading">
                    <h2 id="shipping-heading" class="font-serif text-2xl">Shipping method</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($shippingMethods as $method)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition has-[:checked]:border-umber-700 has-[:checked]:bg-umber-50/40">
                                <input type="radio" name="shipping_method_id" value="{{ $method->id }}" x-model.number="shipping" class="mt-1 border-stone-400 text-umber-700 focus:ring-umber-700" @checked($selectedShipping === $method->id)>
                                <span class="flex-1">
                                    <span class="flex justify-between gap-4 font-semibold">
                                        <span>{{ $method->name }}</span>
                                        <span x-text="fmt(shippingFor({{ $method->id }}), true)">{{ $method->rate ? money($method->rate) : 'Free' }}</span>
                                    </span>
                                    @if ($method->description)<span class="mt-1 block text-sm text-stone-600">{{ $method->description }}</span>@endif
                                    @if ($method->free_over)<span class="mt-1 block text-xs text-stone-500">Free on orders of {{ money($method->free_over) }} or more.</span>@endif
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-stone-600">No shipping options are available right now.</p>
                        @endforelse
                        @error('shipping_method_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section aria-labelledby="payment-heading">
                    <h2 id="payment-heading" class="font-serif text-2xl">Payment</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($paymentMethods as $code => $label)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 has-[:checked]:border-umber-700 has-[:checked]:bg-umber-50/40">
                                <input type="radio" name="payment_method" value="{{ $code }}" class="mt-1 border-stone-400 text-umber-700 focus:ring-umber-700" @checked(old('payment_method', array_key_first($paymentMethods)) === $code)>
                                <span>
                                    <span class="font-semibold">{{ $label }}</span>
                                    @if ($code === 'stripe')
                                        <span class="mt-1 block text-sm text-stone-600">You'll be taken to Stripe's secure page to pay by card or another available method. Your card details never touch our servers.</span>
                                    @else
                                        <span class="mt-1 block text-sm text-stone-600">{{ setting('payments.cod_instructions') ?: 'Pay when your order is delivered.' }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section>
                    <label for="customer_note" class="label">Order notes <span class="font-normal text-stone-500">(optional)</span></label>
                    <textarea id="customer_note" name="customer_note" rows="3" class="input" placeholder="Anything we should know about your order?">{{ old('customer_note') }}</textarea>
                </section>
            </div>

            <aside class="h-fit rounded-2xl border border-gold-200 bg-cream p-6 lg:sticky lg:top-28" aria-label="Order summary">
                <h2 class="font-serif text-xl">Order summary</h2>
                <ul class="mt-4 divide-y divide-gold-100">
                    @foreach ($cart->items as $item)
                        <li class="flex gap-3 py-3">
                            <div class="relative h-16 w-14 shrink-0 overflow-hidden rounded-lg bg-gold-50">
                                <x-store.product-image :product="$item->variant->product" class="h-full w-full object-cover text-[0px]" />
                                <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-ink px-1 text-[0.65rem] font-bold text-white">{{ $item->quantity }}</span>
                            </div>
                            <div class="flex-1 text-sm">
                                <p class="font-semibold">{{ $item->variant->product->name }}</p>
                                @if ($item->variant->name !== 'Default')<p class="text-stone-600">{{ $item->variant->name }}</p>@endif
                            </div>
                            <p class="text-sm font-semibold">{{ money($item->lineTotal()) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2 border-t border-gold-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ money($totals->subtotal) }}</dd></div>
                    @if ($totals->discount > 0 || $totals->freeShippingFromDiscount)
                        <div class="flex justify-between text-umber-700"><dt>Discount ({{ $cart->discountCode?->code }})</dt><dd>{{ $totals->discount ? '-'.money($totals->discount) : 'Free shipping' }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt>Shipping</dt><dd x-text="shipping ? fmt(shippingCost, true) : '—'">—</dd></div>
                    <div class="flex justify-between"><dt>Sales tax <span x-show="taxRate" x-text="'(' + taxRate + '%)'"></span></dt><dd x-text="state ? fmt(tax) : 'Select state'">—</dd></div>
                    <div class="flex justify-between border-t border-gold-200 pt-3 text-base font-semibold"><dt>Total</dt><dd x-text="fmt(total)">{{ money($totals->total()) }}</dd></div>
                </dl>

                @if (setting('checkout.require_terms', true))
                    <label class="mt-6 flex items-start gap-2 text-sm">
                        <input type="checkbox" name="accept_terms" value="1" class="mt-0.5 rounded border-stone-400 text-umber-700 focus:ring-umber-700" @checked(old('accept_terms'))>
                        <span>I agree to the <a href="{{ url('/terms-and-conditions') }}" class="link" target="_blank">terms and conditions</a>, <a href="{{ url('/shipping-policy') }}" class="link" target="_blank">shipping</a> and <a href="{{ url('/returns-policy') }}" class="link" target="_blank">returns</a> policies.</span>
                    </label>
                    @error('accept_terms')<p class="field-error">{{ $message }}</p>@enderror
                @endif

                <button class="btn-primary mt-6 w-full" :disabled="submitting || !shipping" @disabled(! $canCheckout)>
                    <span x-show="!submitting">Place order · <span x-text="fmt(total)">{{ money($totals->total()) }}</span></span>
                    <span x-show="submitting" x-cloak>Processing…</span>
                </button>
                <p class="mt-3 text-center text-xs text-stone-500">Prices in USD. Sales tax is based on the shipping address.</p>
            </aside>
        </form>
    </div>

    @push('scripts')
    <script>
        // Mirrors App\Services\Checkout\PriceCalculator so the customer sees the exact amount
        // the server will charge. The server recalculates and refuses the order if they differ.
        function checkout(cfg) {
            return {
                ...cfg,
                submitting: false,
                get merch() { return Math.max(0, this.subtotal - this.discount); },
                shippingFor(id) {
                    const m = this.methods.find(x => x.id === id);
                    if (!m) return 0;
                    if (this.freeShip) return 0;
                    return (m.free_over !== null && this.merch >= m.free_over) ? 0 : m.rate;
                },
                get shippingCost() { return this.shipping ? this.shippingFor(this.shipping) : 0; },
                get taxRate() { const t = this.taxes[this.state]; return t ? t.rate : 0; },
                get tax() {
                    const t = this.taxes[this.state];
                    if (!t) return 0;
                    return Math.round((this.merch + (t.shipping ? this.shippingCost : 0)) * t.rate / 100);
                },
                get total() { return this.merch + this.shippingCost + this.tax; },
                fmt(cents, freeLabel = false) {
                    if (freeLabel && cents === 0) return 'Free';
                    return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
    @endpush
</x-store-layout>
