<x-admin-layout title="Customers">
    <form method="get" class="mb-4 flex items-end gap-2">
        <div><label for="q" class="label">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Name, email or phone" class="input w-64"></div>
        <button class="btn-outline btn-sm">Search</button>
    </form>
    <h2 class="mb-2 font-sans text-sm font-semibold uppercase tracking-wider text-stone-600">Registered customers</h2>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Spent</th><th class="px-4 py-3">Joined</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($customers as $c)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ $c->name }}@unless($c->is_active) <x-admin.badge>Disabled</x-admin.badge>@endunless</td>
                <td class="px-4 py-3">{{ $c->email }}</td>
                <td class="px-4 py-3">{{ $c->orders_count }}</td>
                <td class="px-4 py-3">{{ money((int) $c->total_spent) }}</td>
                <td class="px-4 py-3 text-stone-600">{{ $c->created_at->format('M j, Y') }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.customers.show', $c) }}" class="text-umber-700 underline">View</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-stone-600">No registered customers yet.</td></tr>
        @endforelse
    </x-admin.table>
    <div class="mt-4">{{ $customers->links() }}</div>

    <h2 class="mb-2 mt-8 font-sans text-sm font-semibold uppercase tracking-wider text-stone-600">Guest checkouts (no account)</h2>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Last order</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($guests as $g)
            <tr>
                <td class="px-4 py-3">{{ $g->first_name }} {{ $g->last_name }}</td>
                <td class="px-4 py-3">{{ $g->email }}</td>
                <td class="px-4 py-3">{{ $g->orders_count }}</td>
                <td class="px-4 py-3 text-stone-600">{{ \Illuminate\Support\Carbon::parse($g->last_order_at)->format('M j, Y') }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.orders.index', ['q' => $g->email]) }}" class="text-umber-700 underline">Orders</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-stone-600">No guest orders.</td></tr>
        @endforelse
    </x-admin.table>
</x-admin-layout>
