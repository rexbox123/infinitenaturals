<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_subtotal', 'starts_at', 'ends_at',
        'usage_limit', 'usage_limit_per_customer', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'min_subtotal' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : strtoupper(trim($value)),
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Returns null when the code may be used, otherwise a customer-facing reason.
     */
    public function unavailableReason(int $subtotal, ?string $email = null): ?string
    {
        $now = now();

        if (! $this->is_active || ($this->starts_at && $this->starts_at->gt($now))) {
            return 'This discount code is not valid.';
        }

        if ($this->ends_at && $this->ends_at->lte($now)) {
            return 'This discount code has expired.';
        }

        if ($this->usage_limit !== null && $this->times_used >= $this->usage_limit) {
            return 'This discount code has reached its usage limit.';
        }

        if ($this->min_subtotal !== null && $subtotal < $this->min_subtotal) {
            return 'This code requires a minimum order of '.money($this->min_subtotal).'.';
        }

        if ($email && $this->usage_limit_per_customer !== null) {
            $used = $this->orders()
                ->where('email', strtolower($email))
                ->whereIn('payment_status', array_map(fn ($s) => $s->value, array_filter(PaymentStatus::cases(), fn ($s) => $s->holdsStock())))
                ->count();

            if ($used >= $this->usage_limit_per_customer) {
                return 'You have already used this discount code.';
            }
        }

        return null;
    }

    /** Discount amount in cents on the given merchandise subtotal. */
    public function amountFor(int $subtotal): int
    {
        return match ($this->type) {
            DiscountType::Percent => (int) floor($subtotal * min(100, $this->value) / 100),
            DiscountType::Fixed => min($this->value, $subtotal),
            DiscountType::FreeShipping => 0,
        };
    }

    public function summary(): string
    {
        return match ($this->type) {
            DiscountType::Percent => "{$this->value}% off",
            DiscountType::Fixed => money($this->value).' off',
            DiscountType::FreeShipping => 'Free shipping',
        };
    }
}
