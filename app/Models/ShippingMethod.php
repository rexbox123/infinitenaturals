<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = ['name', 'description', 'rate', 'free_over', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['rate' => 'integer', 'free_over' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('rate');
    }

    /** Cost in cents for the given merchandise total (after discounts). */
    public function costFor(int $merchandiseTotal): int
    {
        if ($this->free_over !== null && $merchandiseTotal >= $this->free_over) {
            return 0;
        }

        return $this->rate;
    }
}
