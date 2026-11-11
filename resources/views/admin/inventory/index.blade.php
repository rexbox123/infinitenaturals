<x-admin-layout title="Inventory">
    <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
        <div><label for="q" class="label">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Product or SKU" class="input w-56"></div>
        <div><label for="filter" class="label">Show</label>
            <select id="filter" name="filter" class="input w-48">
                <option value="">All</option>
                <option value="low" @selected(request('filter') === 'low')>Low stock (≤ {{ $threshold }})</option>
                <option value="out" @selected(request('filter') === 'out')>Out of stock</option>
            </select></div>
        <button class="btn-outline btn-sm">Filter</button>
    </form>

    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3">On hand</th><th class="px-4 py-3">Quick adjust</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @foreach ($variants as $variant)
            <tr>
                <td class="px-4 py-3">{{ $variant->displayName() }}@unless($variant->is_active) <x-admin.badge>Inactive</x-admin.badge>@endunless</td>
                <td class="px-4 py-3 text-stone-600">{{ $variant->sku ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($variant->track_inventory)
                        <x-admin.badge :color="$variant->stock_quantity <= 0 ? 'red' : ($variant->stock_quantity <= $threshold ? 'amber' : 'green')">{{ $variant->stock_quantity }}</x-admin.badge>
                    @else
                        <span class="text-xs text-stone-500">Not tracked</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($variant->track_inventory)
                        <form method="post" action="{{ route('admin.inventory.adjust', $variant) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="mode" value="add"><input type="hidden" name="reason" value="restock">
                            <label class="sr-only" for="add-{{ $variant->id }}">Units received</label>
                            <input id="add-{{ $variant->id }}" type="number" name="quantity" min="1" placeholder="+ qty" class="input w-24 py-1.5" required>
                            <button class="btn-outline btn-sm">Restock</button>
                        </form>
                    @endif
                </td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.inventory.show', $variant) }}" class="text-umber-700 underline">History &amp; adjust</a></td>
            </tr>
        @endforeach
    </x-admin.table>
    <div class="mt-4">{{ $variants->links() }}</div>

    <x-admin.card title="Recent stock movements" class="mt-6">
        @include('admin.inventory._movements', ['movements' => $recent, 'showItem' => true])
    </x-admin.card>
</x-admin-layout>
