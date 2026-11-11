<x-admin-layout title="Orders">
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'All', 'to_fulfil' => 'To fulfil', 'review' => 'Needs review'] as $key => $label)
            <a href="{{ route('admin.orders.index', array_filter(['view' => $key])) }}" class="rounded-full border px-4 py-1.5 {{ request('view', '') === $key ? 'border-umber-700 bg-umber-700 text-white' : 'border-stone-300 bg-white' }}">{{ $label }}</a>
        @endforeach
    </div>
    <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="hidden" name="view" value="{{ request('view') }}">
        <div><label for="q" class="label">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Order #, name, email, tracking" class="input w-64"></div>
        <div><label for="payment" class="label">Payment</label>
            <select id="payment" name="payment" class="input w-44"><option value="">Any</option>
                @foreach (\App\Enums\PaymentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('payment') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select></div>
        <div><label for="fulfillment" class="label">Fulfilment</label>
            <select id="fulfillment" name="fulfillment" class="input w-44"><option value="">Any</option>
                @foreach (\App\Enums\FulfillmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('fulfillment') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select></div>
        <button class="btn-outline btn-sm">Filter</button>
    </form>

    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Items</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Fulfilment</th><th class="px-4 py-3 text-right">Total</th></tr></x-slot:head>
        @forelse ($orders as $order)
            <tr class="hover:bg-stone-50">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-umber-700">{{ $order->number }}</a>
                    @if ($order->needs_review)<x-admin.badge color="red">Review</x-admin.badge>@endif
                    <span class="block text-xs text-stone-500">{{ $order->created_at->format('M j, Y g:i A') }}</span>
                </td>
                <td class="px-4 py-3">{{ $order->customerName() }}<span class="block text-xs text-stone-500">{{ $order->email }}</span></td>
                <td class="px-4 py-3">{{ $order->items_count }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-admin.badge>@if($order->isCashOnDelivery())<span class="block text-xs text-stone-500">COD</span>@endif</td>
                <td class="px-4 py-3"><x-admin.badge :color="$order->fulfillment_status->color()">{{ $order->fulfillment_status->label() }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right font-semibold">{{ money($order->total) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-stone-600">No orders found.</td></tr>
        @endforelse
    </x-admin.table>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-admin-layout>
