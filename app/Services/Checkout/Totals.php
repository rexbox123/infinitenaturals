<?php

namespace App\Services\Checkout;

/**
 * Immutable result of pricing a cart/order. All amounts in cents.
 */
final class Totals
{
    public function __construct(
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly ?int $shipping,
        public readonly float $taxRate,
        public readonly int $tax,
        public readonly ?string $discountMessage = null,
        public readonly bool $freeShippingFromDiscount = false,
    ) {}

    public function merchandiseTotal(): int
    {
        return max(0, $this->subtotal - $this->discount);
    }

    /** Grand total; shipping counts as 0 until a method is chosen. */
    public function total(): int
    {
        return $this->merchandiseTotal() + ($this->shipping ?? 0) + $this->tax;
    }
}
