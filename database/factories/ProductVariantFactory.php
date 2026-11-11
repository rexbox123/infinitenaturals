<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Default',
            'sku' => strtoupper(fake()->unique()->bothify('IN-####??')),
            'price' => 2000,
            'track_inventory' => true,
            'is_active' => true,
        ];
    }
}
