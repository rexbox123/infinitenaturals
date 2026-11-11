<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'short_description', 'description', 'benefits', 'ingredients',
        'status', 'is_featured', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Products visible on the storefront. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereHas('variants', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isVisible(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    /** Lowest current price among active variants (in cents). */
    public function fromPrice(): ?int
    {
        return $this->activeVariants->map->currentPrice()->min();
    }

    public function hasPriceRange(): bool
    {
        return $this->activeVariants->map->currentPrice()->unique()->count() > 1;
    }

    public function isOnSale(): bool
    {
        return $this->activeVariants->contains(fn (ProductVariant $v) => $v->isOnSale());
    }

    public function isInStock(): bool
    {
        return $this->activeVariants->contains(fn (ProductVariant $v) => $v->isInStock());
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->activeVariants->first(fn (ProductVariant $v) => $v->isInStock())
            ?? $this->activeVariants->first();
    }

    public function hasMultipleVariants(): bool
    {
        return $this->activeVariants->count() > 1;
    }
}
