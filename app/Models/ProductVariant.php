<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    public const LOW_STOCK_DEFAULT = 5;

    protected $fillable = [
        'product_id', 'name', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at',
        'track_inventory', 'weight_grams', 'is_active', 'sort_order',
    ];

    // stock_quantity is intentionally NOT fillable: change it only through InventoryService
    // so every change is locked and recorded as a stock movement.

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sale_price' => 'integer',
            'stock_quantity' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function isOnSale(): bool
    {
        if ($this->sale_price === null || $this->sale_price >= $this->price) {
            return false;
        }

        $now = now();

        return ($this->sale_starts_at === null || $this->sale_starts_at->lte($now))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($now));
    }

    /** Price a customer pays right now, in cents. */
    public function currentPrice(): int
    {
        return $this->isOnSale() ? $this->sale_price : $this->price;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return ! $this->track_inventory || $this->stock_quantity >= $quantity;
    }

    /** Maximum quantity a customer may put in the cart. */
    public function purchasableQuantity(): int
    {
        $max = config('store.max_quantity_per_line');

        return $this->track_inventory ? max(0, min($max, $this->stock_quantity)) : $max;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory
            && $this->stock_quantity <= (int) setting('inventory.low_stock_threshold', self::LOW_STOCK_DEFAULT);
    }

    public function displayName(): string
    {
        $productName = $this->product?->name ?? '';

        return $this->name && $this->name !== 'Default' ? "{$productName} — {$this->name}" : $productName;
    }
}
