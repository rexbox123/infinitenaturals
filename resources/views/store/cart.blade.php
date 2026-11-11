@php($items = $cart?->items ?? collect())
<x-store-layout title="Your cart" noindex>
    <div class="container-store py-10">
        <h1 class="font-serif text-4xl">Your cart</h1>

        @foreach (array_merge($notices, session('notices', [])) as $notice)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">{{ $notice }}</div>
        @endforeach
        @error('quantity')<div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror

        @if ($items->isEmpty())
            <div class="py-20 text-center">
                <p class="font-serif text-2xl">Your cart is empty.</p>
                <a href="{{ route('shop') }}" class="btn-primary mt-6">Continue shopping</a>
            </div>
        @else
            <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_380px]">
                <ul class="divide-y divide-gold-100 border-y border-gold-100" aria-label="Items in your cart">
                    @foreach ($items as $item)
                        @php($variant = $item->variant)
                        <li class="flex gap-4 py-6">
                            <a href="{{ route('product.show', $variant->product) }}" class="h-28 w-24 shrink-0 overflow-hidden rounded-lg bg-gold-50 sm:h-32 sm:w-28">
                                <x-store.product-image :product="$variant->product" />
                            </a>
                            <div class="flex flex-1 flex-col">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <a href="{{ route('product.show', $variant->product) }}" class="font-serif text-lg hover:text-umber-700">{{ $variant->product->name }}</a>
                                        @if ($variant->name !== 'Default')<p class="text-sm text-stone-600">{{ $variant->name }}</p>@endif
                                        <x-store.price :variant="$variant" class="mt-1 text-sm" />
                                    </div>
                                    <p class="font-semibold">{{ money($item->lineTotal()) }}</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between gap-4 pt-3">
                                    <form method="post" action="{{ route('cart.items.update', $item) }}" class="flex items-center gap-2" x-data>
                                        @csrf @method('patch')
                                        <label for="qty-{{ $item->id }}" class="text-sm text-stone-600">Qty</label>
                                        <select id="qty-{{ $item->id }}" name="quantity" class="input w-20 py-1.5" @change="$el.form.submit()">
                                            @foreach (range(1, max($item->quantity, min(20, $variant->purchasableQuantity()))) as $n)
                                                <option value="{{ $n }}" @selected($n === $item->quantity)>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button class="btn-outline btn-sm">Update</button></noscript>
                                    </form>
                                    <form method="post" action="{{ route('cart.items.destroy', $item) }}">
                                        @csrf @method('delete')
                                        <button class="text-sm text-stone-600 underline underline-offset-4 hover:text-umber-700">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="h-fit rounded-2xl border border-gold-200 bg-cream p-6 lg:sticky lg:top-28" aria-label="Order summary">
                    <h2 class="font-serif text-xl">Order summary</h2>

                    <div class="mt-5">
                        @if ($cart->discountCode && ! $totals->discountMessage)
                            <div class="flex items-center justify-between rounded-lg border border-gold-300 bg-white px-3 py-2 text-sm">
                                <span><span class="font-semibold">{{ $cart->discountCode->code }}</span> — {{ $cart->discountCode->summary() }}</span>
                                <form method="post" action="{{ route('cart.discount.remove') }}">@csrf @method('delete')<button class="text-umber-700 underline">Remove</button></form>
                            </div>
                        @else
                            @if ($cart->discountCode && $totals->discountMessage)
                                <p class="mb-2 text-sm text-amber-800">{{ $cart->discountCode->code }}: {{ $totals->discountMessage }}</p>
                            @endif
                            <form method="post" action="{{ route('cart.discount.apply') }}" class="flex gap-2">
                                @csrf
                                <label for="code" class="sr-only">Discount code</label>
                                <input id="code" name="code" value="{{ old('code') }}" placeholder="Discount code" class="input uppercase" autocomplete="off">
                                <button class="btn-outline btn-sm">Apply</button>
                            </form>
                            @error('code')<p class="field-error">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    <div class="mt-6">@include('store._summary')</div>

                    <a href="{{ route('checkout.show') }}" class="btn-primary mt-6 w-full">Proceed to checkout</a>
                    <a href="{{ route('shop') }}" class="mt-3 block text-center text-sm text-stone-600 underline underline-offset-4">Continue shopping</a>
                </aside>
            </div>
        @endif
    </div>
</x-store-layout>
