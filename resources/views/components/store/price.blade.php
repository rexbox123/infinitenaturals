@props(['product' => null, 'variant' => null, 'size' => 'base'])
@php
    $variant ??= $product?->defaultVariant();
    $classes = $size === 'lg' ? 'text-2xl' : 'text-base';
@endphp
@if ($variant)
    <span {{ $attributes->merge(['class' => "inline-flex flex-wrap items-baseline gap-x-2 {$classes}"]) }}>
        @if ($product && $product->hasPriceRange() && ! request()->routeIs('product.show'))
            <span class="font-semibold text-ink">From {{ money($product->fromPrice()) }}</span>
        @elseif ($variant->isOnSale())
            <span class="font-semibold text-umber-700">{{ money($variant->currentPrice()) }}</span>
            <s class="text-sm text-stone-500"><span class="sr-only">Was </span>{{ money($variant->price) }}</s>
        @else
            <span class="font-semibold text-ink">{{ money($variant->currentPrice()) }}</span>
        @endif
    </span>
@endif
