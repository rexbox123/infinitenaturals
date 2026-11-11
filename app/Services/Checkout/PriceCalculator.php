<?php

namespace App\Services\Checkout;

use App\Models\DiscountCode;
use App\Models\ShippingMethod;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

/**
 * Single source of truth for order maths, used by the cart, checkout page and
 * order placement so the customer always sees the amount they are charged.
 *
 *  subtotal     = Σ current unit price × quantity
 *  discount     = from the discount code, on merchandise only
 *  shipping     = method rate, or 0 when merchandise (after discount) ≥ method's free_over,
 *                 or 0 with a free-shipping code
 *  tax          = state rate × (merchandise [+ shipping when the rate says so]), by shipping address
 */
class PriceCalculator
{
    /**
     * @param  Collection<int, array{variant: \App\Models\ProductVariant, quantity: int}>  $lines
     */
    public function calculate(
        Collection $lines,
        ?DiscountCode $discount = null,
        ?ShippingMethod $shipping = null,
        ?string $state = null,
        ?string $email = null,
    ): Totals {
        $subtotal = (int) $lines->sum(fn (array $line) => $line['variant']->currentPrice() * $line['quantity']);

        $discountAmount = 0;
        $discountMessage = null;
        $freeShipping = false;

        if ($discount) {
            $discountMessage = $discount->unavailableReason($subtotal, $email);
            if ($discountMessage === null) {
                $discountAmount = $discount->amountFor($subtotal);
                $freeShipping = $discount->type === \App\Enums\DiscountType::FreeShipping;
            }
        }

        $merchandise = max(0, $subtotal - $discountAmount);

        $shippingAmount = null;
        if ($shipping) {
            $shippingAmount = $freeShipping ? 0 : $shipping->costFor($merchandise);
        }

        $taxRate = 0.0;
        $tax = 0;
        if ($rate = TaxRate::forState($state)) {
            $taxRate = (float) $rate->rate;
            $taxable = $merchandise + ($rate->applies_to_shipping ? ($shippingAmount ?? 0) : 0);
            $tax = (int) round($taxable * $taxRate / 100);
        }

        return new Totals($subtotal, $discountAmount, $shippingAmount, $taxRate, $tax, $discountMessage, $freeShipping);
    }
}
