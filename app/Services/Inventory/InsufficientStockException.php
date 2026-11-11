<?php

namespace App\Services\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $itemName, public readonly int $available)
    {
        parent::__construct($available > 0
            ? "Only {$available} of {$itemName} left in stock. Please update your cart."
            : "{$itemName} just sold out. Please remove it from your cart.");
    }
}
