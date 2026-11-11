@props(['product', 'image' => null, 'eager' => false, 'compact' => false])
@php($image = $image ?? $product->primaryImage ?? $product->images?->first())
@if ($image)
    <img src="{{ $image->url() }}" alt="{{ $image->alt ?: $product->name }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async"
         {{ $attributes->merge(['class' => 'h-full w-full object-cover']) }}>
@else
    {{-- Placeholder until the owner uploads product photography. --}}
    <div {{ $attributes->merge(['class' => 'flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-gold-50 via-cream to-gold-100 text-center '.($compact ? 'p-1' : 'p-6')]) }} role="img" aria-label="{{ $product->name }} (photo coming soon)">
        <svg class="{{ $compact ? 'h-6 w-6' : 'h-10 w-10' }} text-gold-400" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 12c0-4 3-7 7-7 7 0 12 14 19 14 4 0 7-3 7-7s-3-7-7-7c-7 0-12 14-19 14-4 0-7-3-7-7Z" transform="translate(3.5 0)"/></svg>
        @unless ($compact)<span class="mt-3 font-serif text-base leading-snug text-ink/80">{{ $product->name }}</span>@endunless
    </div>
@endif
