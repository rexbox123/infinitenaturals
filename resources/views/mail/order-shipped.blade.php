<x-mail::message>
# Your order is on its way

Hi {{ $order->first_name }}, order **{{ $order->number }}** has shipped.

@if ($order->tracking_number)
**Carrier:** {{ $order->carrierName() }}<br>
**Tracking number:** {{ $order->tracking_number }}

@if ($order->trackingLink())
<x-mail::button :url="$order->trackingLink()">
Track your package
</x-mail::button>
@endif
@endif

@include('mail._items')

<x-mail::button :url="$statusUrl" color="success">
View order status
</x-mail::button>

{{ setting('store.name') }}
</x-mail::message>
