<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Order created, customer has not completed payment yet (or COD awaiting collection). */
    case Pending = 'pending';
    /** Customer completed checkout with a delayed method (e.g. bank debit); funds not yet confirmed. */
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    /** Checkout abandoned/expired or cancelled before payment. */
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Processing => 'Payment processing',
            self::Paid => 'Paid',
            self::Failed => 'Payment failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partially refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Pending, self::Processing => 'amber',
            self::Failed => 'red',
            self::Cancelled, self::Refunded, self::PartiallyRefunded => 'gray',
        };
    }

    /** Whether the order is (or may still become) a valid sale holding stock. */
    public function holdsStock(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Paid, self::PartiallyRefunded], true);
    }
}
