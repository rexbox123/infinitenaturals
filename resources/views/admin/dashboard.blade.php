<x-admin-layout title="Dashboard">
    @if ($checklist)
        <x-admin.card title="Before you go live" class="mb-6 border-amber-300">
            <p class="mb-4 text-sm text-stone-600">These items need information or decisions from the store owner. They disappear once resolved.</p>
            <ul class="space-y-3">
                @foreach ($checklist as $item)
                    <li class="flex gap-3 text-sm">
                        <span class="mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 border-amber-500"></span>
                        <span>
                            @if ($item['url'])<a href="{{ $item['url'] }}" class="font-semibold text-umber-700 underline">{{ $item['label'] }}</a>@else<span class="font-semibold">{{ $item['label'] }}</span>@endif
                            <span class="block text-stone-600">{{ $item['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([['Sales today', money($stats['today'])], ['Sales · 7 days', money($stats['week'])], ['Sales · 30 days', money($stats['month'])], ['Avg. order · 30 days', money($stats['aov'])]] as [$label, $value])
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-ink">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.orders.index', ['view' => 'to_fulfil']) }}" class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm hover:border-umber-700">
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Orders to fulfil</p>
            <p class="mt-2 text-2xl font-semibold {{ $stats['to_fulfil'] ? 'text-umber-700' : 'text-ink' }}">{{ $stats['to_fulfil'] }}</p>
        </a>
        <a href="{{ route('admin.orders.index', ['view' => 'review']) }}" class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm hover:border-umber-700">
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Orders needing review</p>
            <p class="mt-2 text-2xl font-semibold {{ $stats['needs_review'] ? 'text-red-700' : 'text-ink' }}">{{ $stats['needs_review'] }}</p>
        </a>
        @can('messages.view')
            <a href="{{ route('admin.messages.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm hover:border-umber-700">
                <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Unread messages</p>
                <p class="mt-2 text-2xl font-semibold text-ink">{{ $stats['unread_messages'] }}</p>
            </a>
        @endcan
    </div>

    <x-admin.card title="Paid sales · last 14 days" class="mt-6">
        @php($max = max(1, $chart->max()))
        <div class="flex h-40 items-end gap-1 sm:gap-2" role="img" aria-label="Daily paid sales for the last 14 days">
            @foreach ($chart as $date => $cents)
                <div class="group flex flex-1 flex-col items-center justify-end">
                    <span class="mb-1 hidden text-[0.65rem] text-stone-600 group-hover:block">{{ money($cents) }}</span>
                    <div class="w-full rounded-t bg-gold-400 group-hover:bg-umber-700" style="height: {{ max(2, round($cents / $max * 100)) }}%"></div>
                    <span class="mt-1 text-[0.6rem] text-stone-500">{{ \Illuminate\Support\Carbon::parse($date)->format('j') }}</span>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <x-admin.card title="Recent orders">
            <x-slot:actions><a href="{{ route('admin.orders.index') }}" class="text-sm text-umber-700 underline">All orders</a></x-slot:actions>
            @if ($recentOrders->isEmpty())
                <p class="text-sm text-stone-600">No orders yet.</p>
            @else
                <div class="-m-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($recentOrders as $order)
                                <tr class="hover:bg-stone-50">
                                    <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-umber-700">{{ $order->number }}</a><span class="block text-xs text-stone-500">{{ $order->created_at->diffForHumans() }}</span></td>
                                    <td class="px-5 py-3">{{ $order->customerName() }}</td>
                                    <td class="px-5 py-3"><x-admin.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-admin.badge></td>
                                    <td class="px-5 py-3"><x-admin.badge :color="$order->fulfillment_status->color()">{{ $order->fulfillment_status->label() }}</x-admin.badge></td>
                                    <td class="px-5 py-3 text-right font-semibold">{{ money($order->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Low stock">
            <x-slot:actions>@can('inventory.manage')<a href="{{ route('admin.inventory.index', ['filter' => 'low']) }}" class="text-sm text-umber-700 underline">Inventory</a>@endcan</x-slot:actions>
            @forelse ($lowStock as $variant)
                <div class="flex items-center justify-between border-b border-stone-100 py-2 text-sm last:border-0">
                    <span>{{ $variant->displayName() }}</span>
                    <x-admin.badge :color="$variant->stock_quantity <= 0 ? 'red' : 'amber'">{{ $variant->stock_quantity }} left</x-admin.badge>
                </div>
            @empty
                <p class="text-sm text-stone-600">All products are above the low-stock threshold ({{ setting('inventory.low_stock_threshold') }}).</p>
            @endforelse
        </x-admin.card>
    </div>
</x-admin-layout>
