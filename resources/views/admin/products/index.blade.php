<x-admin-layout title="Products">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div><label for="q" class="label">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Name or SKU" class="input w-56"></div>
            <div><label for="status" class="label">Status</label>
                <select id="status" name="status" class="input w-36"><option value="">All</option>
                    @foreach (\App\Enums\ProductStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select></div>
            <div><label for="category" class="label">Category</label>
                <select id="category" name="category" class="input w-44"><option value="">All</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach
                </select></div>
            <button class="btn-outline btn-sm">Filter</button>
            @if (request()->hasAny(['q', 'status', 'category', 'missing']))<a href="{{ route('admin.products.index') }}" class="text-sm underline">Reset</a>@endif
        </form>
        <a href="{{ route('admin.products.create') }}" class="btn-primary btn-sm">Add product</a>
    </div>

    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Stock</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($products as $product)
            @php($v = $product->variants)
            <tr class="hover:bg-stone-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded bg-gold-50"><x-store.product-image :product="$product" class="h-full w-full object-cover" compact /></div>
                        <div>
                            <a href="{{ route('admin.products.edit', $product) }}" class="font-semibold text-ink hover:text-umber-700">{{ $product->name }}</a>
                            <span class="block text-xs text-stone-500">{{ $v->count() }} {{ Str::plural('variant', $v->count()) }} · SKU {{ $v->pluck('sku')->filter()->implode(', ') ?: '—' }}</span>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">{{ money($v->min('price')) }}@if($v->contains(fn ($x) => $x->isOnSale())) <x-admin.badge color="red">Sale</x-admin.badge>@endif</td>
                <td class="px-4 py-3">{{ $v->where('track_inventory', true)->sum('stock_quantity') }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$product->status->color()">{{ $product->status->label() }}</x-admin.badge>@if($product->is_featured) <x-admin.badge color="amber">Featured</x-admin.badge>@endif</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.products.edit', $product) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-stone-600">No products found.</td></tr>
        @endforelse
    </x-admin.table>
    <div class="mt-4">{{ $products->links() }}</div>
</x-admin-layout>
