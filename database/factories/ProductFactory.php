<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'status' => ProductStatus::Active,
            'is_featured' => false,
        ];
    }

    /** Product with one variant at the given price (cents) and stock. */
    public function withVariant(int $price = 2000, int $stock = 10, array $attributes = []): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $stock, $attributes) {
            $variant = ProductVariant::factory()->for($product)->create(['price' => $price] + $attributes);
            $variant->forceFill(['stock_quantity' => $stock])->save();
        });
    }
}
