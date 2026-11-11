<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->displayName() }} | {{ $item->quantity }} | {{ money($item->line_total) }} |
@endforeach
| Subtotal | | {{ money($order->subtotal) }} |
@if ($order->discount_total > 0)
| Discount ({{ $order->discount_code }}) | | -{{ money($order->discount_total) }} |
@endif
| Shipping ({{ $order->shipping_method_name }}) | | {{ $order->shipping_total ? money($order->shipping_total) : 'Free' }} |
@if ($order->tax_total > 0)
| Sales tax | | {{ money($order->tax_total) }} |
@endif
| **Total** | | **{{ money($order->total) }}** |
</x-mail::table>
