<div class="-m-5 overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-stone-50 text-left text-xs uppercase tracking-wider text-stone-600"><tr>
            <th class="px-5 py-2">When</th>@if($showItem ?? false)<th class="px-5 py-2">Item</th>@endif<th class="px-5 py-2">Change</th><th class="px-5 py-2">After</th><th class="px-5 py-2">Reason</th><th class="px-5 py-2">By</th>
        </tr></thead>
        <tbody class="divide-y divide-stone-100">
            @forelse ($movements as $m)
                <tr>
                    <td class="px-5 py-2 text-stone-600">{{ $m->created_at->format('M j, g:i A') }}</td>
                    @if($showItem ?? false)<td class="px-5 py-2">{{ $m->variant?->displayName() }}</td>@endif
                    <td class="px-5 py-2 font-semibold {{ $m->quantity_change < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</td>
                    <td class="px-5 py-2">{{ $m->quantity_after }}</td>
                    <td class="px-5 py-2">{{ $m->reasonLabel() }}@if($m->order) · <a href="{{ route('admin.orders.show', $m->order) }}" class="text-umber-700 underline">{{ $m->order->number }}</a>@endif @if($m->note)<span class="block text-xs text-stone-500">{{ $m->note }}</span>@endif</td>
                    <td class="px-5 py-2 text-stone-600">{{ $m->user?->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-4 text-stone-600">No stock movements yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
