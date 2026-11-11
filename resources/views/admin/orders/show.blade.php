@php
    use App\Enums\PaymentStatus;
    $paid = in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded]);
@endphp
<x-admin-layout :title="'Order '.$order->number">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.orders.index') }}" class="text-sm underline">&larr; Orders</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank" class="btn-outline btn-sm">Packing slip</a>
            @can('orders.manage')
                <form method="post" action="{{ route('admin.orders.resend', $order) }}">@csrf<button class="btn-outline btn-sm">Resend confirmation</button></form>
            @endcan
        </div>
    </div>

    @if ($order->needs_review)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <span><strong>Needs review.</strong> See the timeline below for details.</span>
            @can('orders.manage')<form method="post" action="{{ route('admin.orders.review', $order) }}">@csrf<button class="underline">Mark reviewed</button></form>@endcan
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <x-admin.card title="Items">
                <div class="-m-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold">{{ $item->displayName() }}</span>
                                        <span class="block text-xs text-stone-500">SKU {{ $item->sku ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">{{ $item->quantity }} × {{ money($item->unit_price) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold">{{ money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="mt-8 ml-auto max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd></div>
                    @if ($order->discount_total)<div class="flex justify-between"><dt>Discount ({{ $order->discount_code }})</dt><dd>-{{ money($order->discount_total) }}</dd></div>@endif
                    <div class="flex justify-between"><dt>Shipping ({{ $order->shipping_method_name }})</dt><dd>{{ money($order->shipping_total) }}</dd></div>
                    <div class="flex justify-between"><dt>Tax ({{ rtrim(rtrim((string) $order->tax_rate, '0'), '.') }}%)</dt><dd>{{ money($order->tax_total) }}</dd></div>
                    <div class="flex justify-between border-t pt-1 font-semibold"><dt>Total</dt><dd>{{ money($order->total) }}</dd></div>
                    @if ($order->amount_refunded)<div class="flex justify-between text-red-700"><dt>Refunded</dt><dd>-{{ money($order->amount_refunded) }}</dd></div>@endif
                </dl>
            </x-admin.card>

            @can('orders.manage')
                <x-admin.card title="Fulfilment &amp; tracking">
                    @if ($order->fulfillment_status->value === 'cancelled')
                        <p class="text-sm text-stone-600">This order is cancelled.</p>
                    @else
                        <form method="post" action="{{ route('admin.orders.fulfillment', $order) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf @method('patch')
                            <div>
                                <label for="fulfillment_status" class="label">Status</label>
                                <select id="fulfillment_status" name="fulfillment_status" class="input">
                                    @foreach (['unfulfilled', 'processing', 'shipped', 'delivered'] as $s)
                                        <option value="{{ $s }}" @selected($order->fulfillment_status->value === $s)>{{ \App\Enums\FulfillmentStatus::from($s)->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="tracking_carrier" class="label">Carrier</label>
                                <select id="tracking_carrier" name="tracking_carrier" class="input"><option value="">—</option>
                                    @foreach ($carriers as $key => $c)<option value="{{ $key }}" @selected(old('tracking_carrier', $order->tracking_carrier ?? 'usps') === $key)>{{ $c['name'] }}</option>@endforeach
                                </select>
                            </div>
                            <x-admin.field name="tracking_number" label="Tracking number" :value="$order->tracking_number" />
                            <x-admin.field name="tracking_url" label="Tracking link (optional)" :value="$order->tracking_url" help="Only needed for carriers without a built-in link." />
                            <x-admin.checkbox name="notify_customer" label="Email the customer when marked shipped" :checked="true" class="sm:col-span-2" />
                            <div class="sm:col-span-2"><button class="btn-primary btn-sm">Update fulfilment</button></div>
                        </form>
                    @endif
                </x-admin.card>
            @endcan

            <x-admin.card title="Timeline">
                @can('orders.manage')
                    <form method="post" action="{{ route('admin.orders.notes', $order) }}" class="mb-5 space-y-2">
                        @csrf
                        <label for="message" class="sr-only">Add a note</label>
                        <textarea id="message" name="message" rows="2" class="input" placeholder="Add an internal note…" required></textarea>
                        <div class="flex items-center justify-between">
                            <x-admin.checkbox name="is_customer_visible" label="Show to customer on order page" />
                            <button class="btn-outline btn-sm">Add note</button>
                        </div>
                    </form>
                @endcan
                <ol class="space-y-3 border-l-2 border-gold-200 pl-4 text-sm">
                    @foreach ($order->events as $event)
                        <li>
                            <span class="text-xs text-stone-500">{{ $event->created_at->format('M j, Y g:i A') }} · {{ $event->user?->name ?? 'System' }} · {{ $event->type }}@if($event->is_customer_visible) · <span class="text-umber-700">visible to customer</span>@endif</span>
                            <p>{{ $event->message }}</p>
                        </li>
                    @endforeach
                </ol>
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="Status">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Payment</dt><dd><x-admin.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-admin.badge></dd></div>
                    <div class="flex justify-between"><dt>Fulfilment</dt><dd><x-admin.badge :color="$order->fulfillment_status->color()">{{ $order->fulfillment_status->label() }}</x-admin.badge></dd></div>
                    <div class="flex justify-between"><dt>Method</dt><dd>{{ $order->paymentMethodLabel() }}</dd></div>
                    @if ($order->paid_at)<div class="flex justify-between"><dt>Paid</dt><dd>{{ $order->paid_at->format('M j, g:i A') }}</dd></div>@endif
                    @if ($order->stripe_payment_intent_id)<div class="flex justify-between gap-2"><dt>Stripe</dt><dd class="truncate font-mono text-xs">{{ $order->stripe_payment_intent_id }}</dd></div>@endif
                </dl>
                @can('orders.manage')
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($order->isCashOnDelivery() && $order->payment_status === PaymentStatus::Pending)
                            <form method="post" action="{{ route('admin.orders.cash-collected', $order) }}" onsubmit="return confirm('Record that cash was collected for this order?')">@csrf<button class="btn-primary btn-sm">Mark cash collected</button></form>
                        @endif
                        @if ($order->stripe_checkout_session_id && $stripeConfigured)
                            <form method="post" action="{{ route('admin.orders.sync-payment', $order) }}">@csrf<button class="btn-outline btn-sm">Check payment with Stripe</button></form>
                        @endif
                    </div>
                @endcan
            </x-admin.card>

            <x-admin.card title="Customer">
                <p class="font-semibold">{{ $order->customerName() }}</p>
                <p class="text-sm"><a href="mailto:{{ $order->email }}" class="text-umber-700 underline">{{ $order->email }}</a></p>
                @if ($order->phone)<p class="text-sm">{{ $order->phone }}</p>@endif
                @if ($order->user && auth()->user()->can('customers.manage'))<a href="{{ route('admin.customers.show', $order->user) }}" class="mt-2 inline-block text-sm text-umber-700 underline">Customer profile</a>@else<p class="mt-2 text-xs text-stone-500">Guest checkout</p>@endif
                <h3 class="mt-4 font-sans text-xs font-semibold uppercase tracking-wider text-stone-500">Ship to</h3>
                <address class="mt-1 text-sm not-italic">{{ $order->address_line1 }}<br>@if($order->address_line2){{ $order->address_line2 }}<br>@endif{{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}</address>
                @if ($order->customer_note)
                    <h3 class="mt-4 font-sans text-xs font-semibold uppercase tracking-wider text-stone-500">Customer note</h3>
                    <p class="mt-1 text-sm">{{ $order->customer_note }}</p>
                @endif
            </x-admin.card>

            @can('orders.refund')
                @if ($paid)
                    <x-admin.card title="Refund">
                        <form method="post" action="{{ route('admin.orders.refund', $order) }}" class="space-y-3" onsubmit="return confirm('Issue this refund? It cannot be undone.')">
                            @csrf
                            <x-admin.field name="amount" label="Amount ($)" :value="\App\Support\Money::toDecimal($order->total - $order->amount_refunded)" help="Full remaining amount by default." />
                            <x-admin.field name="reason" label="Reason (internal)" />
                            <x-admin.checkbox name="restock" label="Return items to stock" />
                            <button class="btn btn-sm w-full border border-red-300 bg-white text-red-700 hover:bg-red-50">{{ $order->isCashOnDelivery() ? 'Record refund' : 'Refund via Stripe' }}</button>
                        </form>
                    </x-admin.card>
                @endif
            @endcan

            @can('orders.manage')
                @if ($order->isCancellable() && (! $paid || $order->isCashOnDelivery()))
                    <x-admin.card title="Cancel order">
                        <form method="post" action="{{ route('admin.orders.cancel', $order) }}" class="space-y-3" onsubmit="return confirm('Cancel this order?')">
                            @csrf
                            <x-admin.field name="reason" label="Reason (shown to customer)" />
                            <x-admin.checkbox name="restock" label="Return items to stock" :checked="true" />
                            <button class="btn btn-sm w-full border border-red-300 bg-white text-red-700 hover:bg-red-50">Cancel order</button>
                        </form>
                    </x-admin.card>
                @endif
            @endcan
        </div>
    </div>
</x-admin-layout>
