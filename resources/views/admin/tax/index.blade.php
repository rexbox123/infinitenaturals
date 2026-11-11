<x-admin-layout title="Sales tax">
    <div class="mb-4 flex justify-between gap-3">
        <p class="max-w-2xl text-sm text-stone-600">Tax is charged by the shipping address state, using the rates below. States without a rate are not taxed. Which states you must collect in (nexus) and the correct combined rates are decisions for you and your accountant.</p>
        <a href="{{ route('admin.tax-rates.create') }}" class="btn-primary btn-sm shrink-0">Add rate</a>
    </div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">State</th><th class="px-4 py-3">Rate</th><th class="px-4 py-3">Taxes shipping</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($rates as $r)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ config('store.shipping_states.'.$r->state) }} ({{ $r->state }})</td>
                <td class="px-4 py-3">{{ rtrim(rtrim((string) $r->rate, '0'), '.') }}%</td>
                <td class="px-4 py-3">{{ $r->applies_to_shipping ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$r->is_active ? 'green' : 'gray'">{{ $r->is_active ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.tax-rates.edit', $r) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-stone-600">No tax rates configured — no sales tax is being charged.</td></tr>
        @endforelse
    </x-admin.table>
</x-admin-layout>
