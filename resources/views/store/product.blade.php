@php
    $variants = $product->activeVariants;
    $selected = $product->defaultVariant();
    $variantData = $variants->map(fn ($v) => [
        'id' => $v->id, 'name' => $v->name, 'price' => money($v->currentPrice()),
        'was' => $v->isOnSale() ? money($v->price) : null, 'max' => $v->purchasableQuantity(),
        'sku' => $v->sku,
    ])->values();
    $tabs = collect(['description' => 'Description', 'benefits' => 'Benefits', 'ingredients' => 'Ingredients'])
        ->filter(fn ($label, $field) => filled($product->{$field}));
@endphp
<x-store-layout :title="$product->meta_title ?: $product->name" :description="$product->meta_description ?: $product->short_description" :image="$product->images->first()?->url()">
    @push('head')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name,
            'description' => $product->short_description, 'sku' => $selected?->sku,
            'image' => $product->images->map->url()->all(),
            'brand' => ['@type' => 'Brand', 'name' => setting('store.name')],
            'offers' => ['@type' => 'Offer', 'priceCurrency' => config('store.currency'),
                'price' => number_format(($selected?->currentPrice() ?? 0) / 100, 2, '.', ''),
                'availability' => $product->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => route('product.show', $product)],
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush

    <div class="container-store py-6 sm:py-10">
        <nav class="text-xs uppercase tracking-wider text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-umber-700">Home</a> /
            <a href="{{ route('shop') }}" class="hover:text-umber-700">Shop</a>
            @if ($product->category) / <a href="{{ route('category.show', $product->category) }}" class="hover:text-umber-700">{{ $product->category->name }}</a>@endif
        </nav>

        @unless ($product->isVisible())
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Preview: this product is {{ $product->status->label() }} and not visible to customers.</div>
        @endunless

        <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-14">
            {{-- Gallery --}}
            <div x-data="{ active: 0 }">
                <div class="aspect-square overflow-hidden rounded-2xl bg-gold-50">
                    @forelse ($product->images as $image)
                        <div x-show="active === {{ $loop->index }}" @if(! $loop->first) x-cloak @endif class="h-full">
                            <x-store.product-image :product="$product" :image="$image" :eager="$loop->first" />
                        </div>
                    @empty
                        <x-store.product-image :product="$product" />
                    @endforelse
                </div>
                @if ($product->images->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($product->images as $image)
                            <button type="button" @click="active = {{ $loop->index }}" class="aspect-square overflow-hidden rounded-lg border-2" :class="active === {{ $loop->index }} ? 'border-umber-700' : 'border-transparent'" aria-label="Show photo {{ $loop->iteration }}">
                                <img src="{{ $image->url() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Buy box --}}
            <div x-data="{ variants: {{ Js::from($variantData) }}, selectedId: {{ $selected?->id ?? 'null' }}, qty: 1,
                    get v() { return this.variants.find(x => x.id === this.selectedId) || this.variants[0] } }">
                @if ($product->category)<p class="eyebrow">{{ $product->category->name }}</p>@endif
                <h1 class="mt-2 font-serif text-3xl leading-tight sm:text-4xl">{{ $product->name }}</h1>

                <div class="mt-4 flex items-baseline gap-3 text-2xl" aria-live="polite">
                    <span class="font-semibold" :class="v.was ? 'text-umber-700' : 'text-ink'" x-text="v ? v.price : ''">{{ $selected ? money($selected->currentPrice()) : '' }}</span>
                    <s class="text-base text-stone-500" x-show="v && v.was" x-text="v ? v.was : ''" @if(! $selected?->isOnSale()) x-cloak @endif>{{ $selected?->isOnSale() ? money($selected->price) : '' }}</s>
                </div>

                @if ($product->short_description)
                    <p class="mt-5 leading-relaxed text-stone-700">{{ $product->short_description }}</p>
                @endif

                @if ($selected)
                    <form method="post" action="{{ route('cart.items.store') }}" class="mt-8 space-y-6">
                        @csrf
                        @if ($variants->count() > 1)
                            <fieldset>
                                <legend class="label">Option</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($variants as $variant)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="variant_id" value="{{ $variant->id }}" class="peer sr-only" x-model.number="selectedId" @checked($variant->is($selected))>
                                            <span class="block rounded-full border border-stone-300 px-4 py-2 text-sm peer-checked:border-umber-700 peer-checked:bg-umber-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-umber-700 {{ $variant->isInStock() ? '' : 'line-through opacity-60' }}">{{ $variant->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @else
                            <input type="hidden" name="variant_id" value="{{ $selected->id }}">
                            @if ($selected->name !== 'Default')<p class="text-sm text-stone-600">Size: <span class="font-semibold text-ink">{{ $selected->name }}</span></p>@endif
                        @endif

                        <p class="text-sm" aria-live="polite">
                            <template x-if="v.max > 5"><span class="font-semibold text-green-700">In stock</span></template>
                            <template x-if="v.max > 0 && v.max <= 5"><span class="font-semibold text-amber-700">Only <span x-text="v.max"></span> left</span></template>
                            <template x-if="v.max === 0"><span class="font-semibold text-stone-600">Sold out</span></template>
                        </p>

                        <div class="flex flex-col gap-3 sm:flex-row" x-show="v.max > 0">
                            <div class="flex h-12 w-full items-center rounded-full border border-stone-300 sm:w-36">
                                <button type="button" class="h-full w-12 text-xl" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                                <label for="qty" class="sr-only">Quantity</label>
                                <input id="qty" type="number" name="quantity" x-model.number="qty" min="1" :max="v.max" class="h-full w-full border-0 text-center focus:ring-0" inputmode="numeric">
                                <button type="button" class="h-full w-12 text-xl" @click="qty = Math.min(v.max, qty + 1)" aria-label="Increase quantity">+</button>
                            </div>
                            <button class="btn-primary h-12 flex-1">Add to cart</button>
                        </div>
                        @error('quantity')<p class="field-error">{{ $message }}</p>@enderror
                        @error('variant_id')<p class="field-error">{{ $message }}</p>@enderror
                    </form>
                @endif

                <ul class="mt-8 space-y-2 border-t border-gold-100 pt-6 text-sm text-stone-600">
                    <li>Free USPS Standard shipping on orders over $50 (U.S. only).</li>
                    <li>Returns accepted within 30 days on unopened, unused items. <a href="{{ url('/returns-policy') }}" class="link">Details</a></li>
                    <li x-show="v && v.sku">SKU: <span x-text="v ? v.sku : ''"></span></li>
                </ul>
            </div>
        </div>

        {{-- Description / Benefits / Ingredients --}}
        @if ($tabs->isNotEmpty())
            <section class="mt-14" x-data="{ tab: '{{ $tabs->keys()->first() }}' }">
                <div class="flex gap-6 overflow-x-auto border-b border-gold-200" role="tablist">
                    @foreach ($tabs as $field => $label)
                        <button type="button" role="tab" id="tab-{{ $field }}" aria-controls="panel-{{ $field }}" @click="tab = '{{ $field }}'"
                                :aria-selected="tab === '{{ $field }}'" class="-mb-px shrink-0 border-b-2 py-3 text-sm font-semibold uppercase tracking-wider"
                                :class="tab === '{{ $field }}' ? 'border-umber-700 text-umber-700' : 'border-transparent text-stone-500 hover:text-ink'">{{ $label }}</button>
                    @endforeach
                </div>
                @foreach ($tabs as $field => $label)
                    <div id="panel-{{ $field }}" role="tabpanel" aria-labelledby="tab-{{ $field }}" x-show="tab === '{{ $field }}'" @if(! $loop->first) x-cloak @endif
                         class="prose prose-store max-w-3xl py-8">
                        {!! $product->{$field} !!}
                    </div>
                @endforeach
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-10 border-t border-gold-200 pt-14">
                <h2 class="section-title text-center">You may also like</h2>
                <div class="divider-gold"></div>
                <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4">
                    @foreach ($related as $item)
                        <x-store.product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-store-layout>
