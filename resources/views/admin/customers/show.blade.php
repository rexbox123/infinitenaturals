<x-admin-layout :title="$customer->name">
    <a href="{{ route('admin.customers.index') }}" class="text-sm underline">&larr; Customers</a>
    <div class="mt-4 grid gap-6 lg:grid-cols-[320px_1fr]">
        <x-admin.card title="Profile">
            <p class="font-semibold">{{ $customer->name }}</p>
            <p class="text-sm"><a class="text-umber-700 underline" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></p>
            <p class="text-sm">{{ $customer->phone ?? 'No phone' }}</p>
            <p class="mt-2 text-sm text-stone-600">Customer since {{ $customer->created_at->format('M j, Y') }}</p>
            <p class="mt-2 text-sm">Lifetime spend: <strong>{{ money($totalSpent) }}</strong></p>
            <p class="mt-2"><x-admin.badge :color="$customer->is_active ? 'green' : 'gray'">{{ $customer->is_active ? 'Active' : 'Disabled' }}</x-admin.badge></p>
            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn-outline btn-sm mt-4">Edit</a>
        </x-admin.card>
        <x-admin.card title="Order history">
            <div class="-m-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-umber-700">{{ $order->number }}</a>@if(! $order->user_id)<span class="block text-xs text-stone-500">Placed as guest</span>@endif</td>
                                <td class="px-5 py-3">{{ $order->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3"><x-admin.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-admin.badge></td>
                                <td class="px-5 py-3"><x-admin.badge :color="$order->fulfillment_status->color()">{{ $order->fulfillment_status->label() }}</x-admin.badge></td>
                                <td class="px-5 py-3 text-right font-semibold">{{ money($order->total) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-4 text-stone-600">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>
</x-admin-layout>
