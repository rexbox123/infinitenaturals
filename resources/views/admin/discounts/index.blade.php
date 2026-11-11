<x-admin-layout title="Discount codes">
    <div class="mb-4 flex justify-between gap-3">
        <p class="text-sm text-stone-600">Codes customers enter in the cart. For sale prices, edit a product's variants; for homepage promotions, use Banners.</p>
        <a href="{{ route('admin.discounts.create') }}" class="btn-primary btn-sm shrink-0">New code</a>
    </div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Conditions</th><th class="px-4 py-3">Used</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($discounts as $d)
            @php($expired = $d->ends_at && $d->ends_at->isPast())
            <tr>
                <td class="px-4 py-3 font-mono font-semibold">{{ $d->code }}<span class="block font-sans text-xs font-normal text-stone-500">{{ $d->description }}</span></td>
                <td class="px-4 py-3">{{ $d->summary() }}</td>
                <td class="px-4 py-3 text-xs text-stone-600">
                    @if($d->min_subtotal)Min. {{ money($d->min_subtotal) }}<br>@endif
                    @if($d->starts_at || $d->ends_at){{ $d->starts_at?->format('M j') ?? '…' }} – {{ $d->ends_at?->format('M j, Y') ?? '…' }}<br>@endif
                    @if($d->usage_limit_per_customer){{ $d->usage_limit_per_customer }} per customer @endif
                </td>
                <td class="px-4 py-3">{{ $d->times_used }}{{ $d->usage_limit ? ' / '.$d->usage_limit : '' }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$d->is_active && ! $expired ? 'green' : 'gray'">{{ $expired ? 'Expired' : ($d->is_active ? 'Active' : 'Inactive') }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.discounts.edit', $d) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-stone-600">No discount codes yet.</td></tr>
        @endforelse
    </x-admin.table>
    <div class="mt-4">{{ $discounts->links() }}</div>
</x-admin-layout>
