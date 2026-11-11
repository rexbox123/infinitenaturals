<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing slip {{ $order->number }}</title>
    <meta name="robots" content="noindex">
    <style>
        body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; color: #211a16; max-width: 760px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-family: Georgia, serif; font-weight: normal; letter-spacing: .15em; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e7e5e4; }
        .muted { color: #57534e; font-size: .9rem; }
        .grid { display: flex; justify-content: space-between; gap: 2rem; margin-top: 1.5rem; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <button class="noprint" onclick="window.print()">Print</button>
    <h1>INFINITE NATURALS</h1>
    <p class="muted">{{ setting('store.address') }} · {{ setting('store.phone') }} · {{ setting('store.email') }}</p>
    <div class="grid">
        <div><strong>Ship to</strong><br>{{ $order->customerName() }}<br>{{ $order->address_line1 }}<br>@if($order->address_line2){{ $order->address_line2 }}<br>@endif{{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}</div>
        <div><strong>Order {{ $order->number }}</strong><br>{{ $order->created_at->format('F j, Y') }}<br>{{ $order->shipping_method_name }}</div>
    </div>
    <table>
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th></tr></thead>
        <tbody>@foreach ($order->items as $item)<tr><td>{{ $item->displayName() }}</td><td>{{ $item->sku ?? '—' }}</td><td>{{ $item->quantity }}</td></tr>@endforeach</tbody>
    </table>
    @if ($order->customer_note)<p><strong>Customer note:</strong> {{ $order->customer_note }}</p>@endif
    <p class="muted">Thank you for shopping with {{ setting('store.name') }}. Returns accepted within 30 days on unopened, unused items — see {{ url('/returns-policy') }}.</p>
</body>
</html>
