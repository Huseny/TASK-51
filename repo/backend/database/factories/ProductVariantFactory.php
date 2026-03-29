<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'label' => fake()->randomElement(['Standard', 'Premium', 'Bundle']),
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => fake()->numberBetween(5, 50),
            'presale_available_date' => null,
        ];
    }
}
