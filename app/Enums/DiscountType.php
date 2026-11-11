<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage off merchandise',
            self::Fixed => 'Fixed amount off merchandise',
            self::FreeShipping => 'Free shipping',
        };
    }
}
