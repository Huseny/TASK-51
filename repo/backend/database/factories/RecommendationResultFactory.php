<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RecommendationModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecommendationResult>
 */
class RecommendationResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_version_id' => RecommendationModel::factory(),
            'user_id' => User::factory(),
            'item_id' => Product::factory(),
            'score' => fake()->randomFloat(4, 0, 1),
            'rank_order' => fake()->numberBetween(1, 10),
            'is_exploration' => false,
            'created_at' => now(),
        ];
    }
}
