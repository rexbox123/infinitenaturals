<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_refunded' => 'integer',
            'tax_rate' => 'decimal:4',
            'needs_review' => 'boolean',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Orders that count as sales in reports. */
    public function scopeSales(Builder $query): void
    {
        $query->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded]);
    }

    public function customerName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_method === 'cod';
    }

    public function isAwaitingOnlinePayment(): bool
    {
        return $this->payment_method === 'stripe' && $this->payment_status === PaymentStatus::Pending;
    }

    public function isCancellable(): bool
    {
        return ! in_array($this->fulfillment_status, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered, FulfillmentStatus::Cancelled], true);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function paymentMethodLabel(): string
    {
        return $this->isCashOnDelivery() ? 'Cash on delivery' : 'Card / online payment (Stripe)';
    }

    /** Customer-facing summary of where the order is. */
    public function statusHeadline(): string
    {
        if ($this->fulfillment_status === FulfillmentStatus::Cancelled || $this->payment_status === PaymentStatus::Cancelled) {
            return 'Cancelled';
        }

        return match (true) {
            $this->payment_status === PaymentStatus::Failed => 'Payment failed',
            $this->payment_status === PaymentStatus::Refunded => 'Refunded',
            $this->fulfillment_status === FulfillmentStatus::Delivered => 'Delivered',
            $this->fulfillment_status === FulfillmentStatus::Shipped => 'Shipped',
            $this->payment_status === PaymentStatus::Processing => 'Payment processing',
            $this->payment_status === PaymentStatus::Pending && ! $this->isCashOnDelivery() => 'Awaiting payment',
            default => 'Order received — preparing for shipment',
        };
    }

    public function addEvent(string $type, string $message, bool $customerVisible = false, ?User $user = null): OrderEvent
    {
        return $this->events()->create([
            'type' => $type,
            'message' => $message,
            'is_customer_visible' => $customerVisible,
            'user_id' => $user?->id,
        ]);
    }

    public function trackingLink(): ?string
    {
        if ($this->tracking_url) {
            return $this->tracking_url;
        }

        $template = config("store.tracking_carriers.{$this->tracking_carrier}.url");

        return $template && $this->tracking_number
            ? str_replace('{number}', rawurlencode($this->tracking_number), $template)
            : null;
    }

    public function carrierName(): ?string
    {
        return config("store.tracking_carriers.{$this->tracking_carrier}.name", $this->tracking_carrier);
    }
}
