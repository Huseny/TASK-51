<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecommendationModel>
 */
class RecommendationModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->numberBetween(1, 1000),
            'is_active' => false,
            'feature_snapshot' => ['users' => 0, 'items' => 0],
            'created_at' => now(),
        ];
    }
}
