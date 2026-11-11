{{-- Order totals block shared by cart. --}}
<dl class="space-y-3 text-sm">
    <div class="flex justify-between"><dt>Subtotal</dt><dd class="font-semibold">{{ money($totals->subtotal) }}</dd></div>
    @if ($totals->discount > 0 || $totals->freeShippingFromDiscount)
        <div class="flex justify-between text-umber-700"><dt>Discount</dt><dd>{{ $totals->discount > 0 ? '-'.money($totals->discount) : 'Free shipping' }}</dd></div>
    @endif
    <div class="flex justify-between text-stone-600"><dt>Shipping</dt><dd>Calculated at checkout</dd></div>
    <div class="flex justify-between text-stone-600"><dt>Sales tax</dt><dd>Calculated at checkout</dd></div>
    <div class="flex justify-between border-t border-gold-200 pt-3 text-base"><dt class="font-semibold">Estimated total</dt><dd class="font-semibold">{{ money($totals->merchandiseTotal()) }}</dd></div>
</dl>
