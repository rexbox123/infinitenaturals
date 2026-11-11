@props(['product'])
@php($variant = $product->defaultVariant())
<article class="group flex flex-col">
    <a href="{{ route('product.show', $product) }}" class="relative block aspect-[4/5] overflow-hidden rounded-xl bg-gold-50">
        <x-store.product-image :product="$product" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
        <div class="absolute left-3 top-3 flex flex-col gap-1">
            @if ($product->isOnSale())
                <span class="rounded-full bg-umber-700 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-white">Sale</span>
            @endif
            @unless ($product->isInStock())
                <span class="rounded-full bg-white/90 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-stone-700">Sold out</span>
            @endunless
        </div>
    </a>
    <div class="mt-4 flex flex-1 flex-col">
        @if ($product->category)
            <p class="eyebrow text-[0.65rem]">{{ $product->category->name }}</p>
        @endif
        <h3 class="mt-1 font-serif text-lg leading-snug">
            <a href="{{ route('product.show', $product) }}" class="hover:text-umber-700">{{ $product->name }}</a>
        </h3>
        <x-store.price :product="$product" class="mt-1" />
        <div class="mt-auto pt-4">
            @if ($variant && $product->isInStock() && ! $product->hasMultipleVariants())
                <form method="post" action="{{ route('cart.items.store') }}">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="btn-outline btn-sm w-full">Add to cart</button>
                </form>
            @else
                <a href="{{ route('product.show', $product) }}" class="btn-outline btn-sm w-full">{{ $product->isInStock() ? 'Choose options' : 'View details' }}</a>
            @endif
        </div>
    </div>
</article>
