@php
    use App\Enums\PaymentStatus;
    use App\Enums\FulfillmentStatus;
    $steps = ['Order placed', 'Payment confirmed', 'Shipped', 'Delivered'];
    $reached = 1;
    if ($order->payment_status === PaymentStatus::Paid || $order->isCashOnDelivery()) $reached = 2;
    if (in_array($order->fulfillment_status, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered])) $reached = 3;
    if ($order->fulfillment_status === FulfillmentStatus::Delivered) $reached = 4;
    $void = in_array($order->payment_status, [PaymentStatus::Failed, PaymentStatus::Cancelled]) || $order->fulfillment_status === FulfillmentStatus::Cancelled;
    if ($order->isCashOnDelivery()) $steps[1] = 'Confirmed (cash on delivery)';
@endphp
<x-store-layout :title="'Order '.$order->number" noindex>
    <div class="container-store max-w-4xl py-10">
        <p class="eyebrow">Order {{ $order->number }}</p>
        <h1 class="mt-2 font-serif text-3xl sm:text-4xl">{{ $order->statusHeadline() }}</h1>
        <p class="mt-2 text-sm text-stone-600">Placed {{ $order->created_at->format('F j, Y \a\t g:i A') }} · Confirmation sent to {{ $order->email }}</p>

        {{-- Payment state messaging --}}
        @if ($order->payment_status === PaymentStatus::Pending && ! $order->isCashOnDelivery())
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="font-semibold">We haven't received payment for this order yet.</p>
                <p class="mt-1 text-sm">If you just paid, this can take a moment to confirm — refresh this page shortly. Unpaid orders are released automatically after {{ max(31, config('store.payment_timeout_minutes')) }} minutes.</p>
                @if ($canResume)
                    <form method="post" action="{{ route('orders.pay', $order) }}" class="mt-4">
                        @csrf <input type="hidden" name="token" value="{{ $token }}">
                        <button class="btn-primary btn-sm">Complete payment</button>
                    </form>
                @endif
            </div>
        @elseif ($order->payment_status === PaymentStatus::Processing)
            <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-5 text-blue-900">
                <p class="font-semibold">Your payment is processing.</p>
                <p class="mt-1 text-sm">Some payment methods take a few business days to clear. We'll email you and start preparing your order as soon as it does.</p>
            </div>
        @elseif (in_array($order->payment_status, [PaymentStatus::Failed, PaymentStatus::Cancelled]))
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-900">
                <p class="font-semibold">{{ $order->payment_status === PaymentStatus::Failed ? 'Your payment did not go through.' : 'This order was cancelled.' }}</p>
                <p class="mt-1 text-sm">You have not been charged for this order. You can put the same items back in your cart and try again.</p>
                <form method="post" action="{{ route('orders.retry', $order) }}" class="mt-4">
                    @csrf <input type="hidden" name="token" value="{{ $token }}">
                    <button class="btn-primary btn-sm">Try again</button>
                </form>
            </div>
        @endif

        {{-- Progress --}}
        @unless ($void)
            <ol class="mt-8 grid grid-cols-4 gap-2" aria-label="Order progress">
                @foreach ($steps as $i => $step)
                    <li class="text-center">
                        <div class="h-1.5 rounded-full {{ $i < $reached ? 'bg-umber-700' : 'bg-stone-200' }}"></div>
                        <p class="mt-2 text-xs {{ $i < $reached ? 'font-semibold text-ink' : 'text-stone-500' }}">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>
        @endunless

        @if ($order->tracking_number)
            <div class="mt-8 rounded-xl border border-gold-200 bg-gold-50 p-5">
                <p class="font-semibold">Tracking</p>
                <p class="mt-1 text-sm">{{ $order->carrierName() }} · {{ $order->tracking_number }}</p>
                @if ($order->trackingLink())<a href="{{ $order->trackingLink() }}" class="btn-primary btn-sm mt-3" target="_blank" rel="noopener">Track package</a>@endif
            </div>
        @endif

        <div class="mt-10 grid gap-8 md:grid-cols-[1fr_280px]">
            <section aria-labelledby="items-heading">
                <h2 id="items-heading" class="font-serif text-xl">Items</h2>
                <ul class="mt-3 divide-y divide-gold-100 border-y border-gold-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-center gap-4 py-4">
                            <div class="h-16 w-14 shrink-0 overflow-hidden rounded-lg bg-gold-50">
                                @if ($item->variant?->product)<x-store.product-image :product="$item->variant->product" class="h-full w-full object-cover" compact />@endif
                            </div>
                            <div class="flex-1 text-sm">
                                <p class="font-semibold">{{ $item->displayName() }}</p>
                                <p class="text-stone-600">{{ $item->quantity }} × {{ money($item->unit_price) }}</p>
                            </div>
                            <p class="text-sm font-semibold">{{ money($item->line_total) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd></div>
                    @if ($order->discount_total)<div class="flex justify-between text-umber-700"><dt>Discount ({{ $order->discount_code }})</dt><dd>-{{ money($order->discount_total) }}</dd></div>@endif
                    <div class="flex justify-between"><dt>Shipping ({{ $order->shipping_method_name }})</dt><dd>{{ $order->shipping_total ? money($order->shipping_total) : 'Free' }}</dd></div>
                    <div class="flex justify-between"><dt>Sales tax</dt><dd>{{ money($order->tax_total) }}</dd></div>
                    <div class="flex justify-between border-t border-gold-200 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ money($order->total) }}</dd></div>
                    @if ($order->amount_refunded)<div class="flex justify-between text-stone-600"><dt>Refunded</dt><dd>-{{ money($order->amount_refunded) }}</dd></div>@endif
                </dl>
            </section>
            <aside class="space-y-6 text-sm">
                <div>
                    <h2 class="font-serif text-lg">Shipping to</h2>
                    <address class="mt-2 not-italic text-stone-700">
                        {{ $order->customerName() }}<br>{{ $order->address_line1 }}<br>
                        @if ($order->address_line2){{ $order->address_line2 }}<br>@endif
                        {{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}
                    </address>
                </div>
                <div>
                    <h2 class="font-serif text-lg">Payment</h2>
                    <p class="mt-2 text-stone-700">{{ $order->paymentMethodLabel() }}<br>{{ $order->payment_status->label() }}</p>
                </div>
                @if ($order->events->isNotEmpty())
                    <div>
                        <h2 class="font-serif text-lg">Updates</h2>
                        <ul class="mt-2 space-y-2">
                            @foreach ($order->events as $event)
                                <li class="text-stone-700"><span class="block text-xs text-stone-500">{{ $event->created_at->format('M j, g:i A') }}</span>{{ $event->message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="text-stone-600">Questions? <a href="{{ route('contact') }}" class="link">Contact us</a> and mention order {{ $order->number }}.</p>
            </aside>
        </div>
    </div>
</x-store-layout>
