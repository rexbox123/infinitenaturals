<x-mail::message>
# New order {{ $order->number }}

**Customer:** {{ $order->customerName() }} ({{ $order->email }}{{ $order->phone ? ', '.$order->phone : '' }})<br>
**Payment:** {{ $order->paymentMethodLabel() }} — {{ $order->payment_status->label() }}<br>
**Shipping:** {{ $order->shipping_method_name }} to {{ $order->city }}, {{ $order->state }}

@include('mail._items')

@if ($order->customer_note)
**Customer note:** {{ $order->customer_note }}
@endif

<x-mail::button :url="$adminUrl">
Open in admin
</x-mail::button>
</x-mail::message>
