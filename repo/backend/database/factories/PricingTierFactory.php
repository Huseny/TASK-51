<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingTier>
 */
class PricingTierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'min_quantity' => 1,
            'max_quantity' => null,
            'unit_price' => fake()->randomFloat(2, 5, 120),
            'created_at' => now(),
        ];
    }
}
