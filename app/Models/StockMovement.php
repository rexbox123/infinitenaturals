<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const REASONS = [
        'initial' => 'Initial stock',
        'restock' => 'Restock / received',
        'adjustment' => 'Manual correction',
        'damaged' => 'Damaged / lost',
        'return' => 'Customer return',
        'order_placed' => 'Order placed',
        'order_released' => 'Order cancelled / unpaid',
    ];

    protected $fillable = ['product_variant_id', 'order_id', 'user_id', 'quantity_change', 'quantity_after', 'reason', 'note'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
