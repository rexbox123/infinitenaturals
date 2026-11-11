<x-admin-layout title="Shipping">
    <div class="mb-4 flex justify-between gap-3">
        <p class="max-w-2xl text-sm text-stone-600">Customers choose from the active methods at checkout. “Free over” applies when the merchandise total (after discounts) reaches the amount. Destinations are limited to the 50 U.S. states (no APO/FPO), per your shipping policy.</p>
        <a href="{{ route('admin.shipping-methods.create') }}" class="btn-primary btn-sm shrink-0">Add method</a>
    </div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Method</th><th class="px-4 py-3">Rate</th><th class="px-4 py-3">Free over</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($methods as $m)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ $m->name }}<span class="block text-xs font-normal text-stone-500">{{ $m->description }}</span></td>
                <td class="px-4 py-3">{{ money($m->rate) }}@if($m->rate === 0 && ! $m->is_active) <x-admin.badge color="amber">Rate not set</x-admin.badge>@endif</td>
                <td class="px-4 py-3">{{ $m->free_over !== null ? money($m->free_over) : '—' }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$m->is_active ? 'green' : 'gray'">{{ $m->is_active ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.shipping-methods.edit', $m) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-stone-600">No shipping methods.</td></tr>
        @endforelse
    </x-admin.table>
</x-admin-layout>
