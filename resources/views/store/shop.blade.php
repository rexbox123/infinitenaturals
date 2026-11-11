@php
    $heading = $category?->name ?? (filled($filters['q'] ?? null) ? 'Search results' : 'Shop all products');
    $activeFilters = collect($filters)->only(['q', 'min', 'max', 'in_stock', 'on_sale'])->filter(fn ($v) => filled($v));
@endphp
<x-store-layout :title="$heading" :description="$category?->description" :canonical="$category ? route('category.show', $category) : route('shop')" :noindex="filled($filters['q'] ?? null)">
    <div class="border-b border-gold-200 bg-cream">
        <div class="container-store py-10 text-center">
            <nav class="text-xs uppercase tracking-wider text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-umber-700">Home</a> / <a href="{{ route('shop') }}" class="hover:text-umber-700">Shop</a>
                @if ($category) / <span class="text-stone-700">{{ $category->name }}</span>@endif
            </nav>
            <h1 class="mt-3 font-serif text-4xl">{{ $heading }}</h1>
            @if ($category?->description)<p class="mx-auto mt-3 max-w-2xl text-stone-600">{{ $category->description }}</p>@endif
            @if (filled($filters['q'] ?? null))<p class="mt-3 text-stone-600">for “{{ $filters['q'] }}”</p>@endif
        </div>
    </div>

    <div class="container-store py-8" x-data="{ filters: false }">
        {{-- Category pills --}}
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
            <a href="{{ route('shop', request()->except(['page'])) }}" class="shrink-0 rounded-full border px-4 py-2 text-sm {{ ! $category ? 'border-umber-700 bg-umber-700 text-white' : 'border-stone-300 hover:border-umber-700' }}">All</a>
            @foreach ($categories as $cat)
                <a href="{{ route('category.show', [$cat] + request()->except(['page'])) }}" class="shrink-0 rounded-full border px-4 py-2 text-sm {{ $category?->is($cat) ? 'border-umber-700 bg-umber-700 text-white' : 'border-stone-300 hover:border-umber-700' }}">{{ $cat->name }}</a>
            @endforeach
        </div>

        <form method="get" class="mt-6 flex flex-col gap-4 border-y border-gold-100 py-4 lg:flex-row lg:items-end lg:justify-between" aria-label="Filter and sort products">
            <div class="flex items-center justify-between gap-3 lg:hidden">
                <button type="button" @click="filters = !filters" class="btn-outline btn-sm" :aria-expanded="filters">
                    Filters @if($activeFilters->isNotEmpty())({{ $activeFilters->count() }})@endif
                </button>
                <p class="text-sm text-stone-600">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:flex lg:items-end" :class="filters ? '' : 'hidden lg:flex'">
                <div>
                    <label for="q" class="label">Search</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or ingredient" class="input lg:w-56">
                </div>
                <fieldset class="flex items-end gap-2">
                    <legend class="label">Price ($)</legend>
                    <label class="sr-only" for="min">Minimum price</label>
                    <input id="min" type="number" inputmode="decimal" min="0" step="1" name="min" value="{{ $filters['min'] ?? '' }}" placeholder="Min" class="input w-24">
                    <span class="pb-2 text-stone-400">–</span>
                    <label class="sr-only" for="max">Maximum price</label>
                    <input id="max" type="number" inputmode="decimal" min="0" step="1" name="max" value="{{ $filters['max'] ?? '' }}" placeholder="Max" class="input w-24">
                </fieldset>
                <div class="flex items-center gap-5 pb-2">
                    <label class="flex items-center gap-2 whitespace-nowrap text-sm"><input type="checkbox" name="in_stock" value="1" @checked(! empty($filters['in_stock'])) class="rounded border-stone-300 text-umber-700 focus:ring-umber-700"> In stock</label>
                    <label class="flex items-center gap-2 whitespace-nowrap text-sm"><input type="checkbox" name="on_sale" value="1" @checked(! empty($filters['on_sale'])) class="rounded border-stone-300 text-umber-700 focus:ring-umber-700"> On sale</label>
                </div>
                <div class="flex gap-2">
                    <button class="btn-primary btn-sm">Apply</button>
                    @if ($activeFilters->isNotEmpty())
                        <a href="{{ $category ? route('category.show', $category) : route('shop') }}" class="btn-outline btn-sm">Clear</a>
                    @endif
                </div>
            </div>

            <div class="flex items-end gap-4">
                <p class="hidden whitespace-nowrap pb-2 text-sm text-stone-600 lg:block">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
                <div class="w-full lg:w-auto">
                    <label for="sort" class="label">Sort by</label>
                    <select id="sort" name="sort" class="input" onchange="this.form.submit()">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        @if ($products->isEmpty())
            <div class="py-20 text-center">
                <p class="font-serif text-2xl">No products match your search.</p>
                <p class="mt-2 text-stone-600">Try a different word or clear the filters.</p>
                <a href="{{ route('shop') }}" class="btn-primary mt-6">View all products</a>
            </div>
        @else
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-12">{{ $products->links() }}</div>
        @endif
    </div>
</x-store-layout>
