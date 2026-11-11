<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['state', 'rate', 'applies_to_shipping', 'is_active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'applies_to_shipping' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function forState(?string $state): ?self
    {
        if (! $state) {
            return null;
        }

        return static::query()->where('state', strtoupper($state))->where('is_active', true)->first();
    }
}
