<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Not yet fulfilled',
            self::Processing => 'Preparing order',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivered, self::Shipped => 'green',
            self::Processing => 'blue',
            self::Unfulfilled => 'amber',
            self::Cancelled => 'gray',
        };
    }
}
