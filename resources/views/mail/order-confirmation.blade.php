<x-mail::message>
# Thank you for your order, {{ $order->first_name }}!

We've received order **{{ $order->number }}**.

@if ($order->isCashOnDelivery())
You chose **cash on delivery**. Please have {{ money($order->total) }} ready when your order arrives.
@if (setting('payments.cod_instructions'))

{{ setting('payments.cod_instructions') }}
@endif
@elseif ($order->payment_status->value === 'processing')
Your payment is **being processed** by your bank. We'll prepare your order as soon as it clears — no action is needed from you.
@else
Your payment has been **confirmed**.
@endif

@include('mail._items')

**Shipping to**
{{ $order->customerName() }}<br>
{{ $order->address_line1 }}@if($order->address_line2), {{ $order->address_line2 }}@endif<br>
{{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}

<x-mail::button :url="$statusUrl">
View order status
</x-mail::button>

Questions? Reply to this email or call us at {{ setting('store.phone') }}.

{{ setting('store.name') }}
</x-mail::message>
