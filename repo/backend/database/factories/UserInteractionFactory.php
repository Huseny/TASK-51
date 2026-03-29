<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserInteraction>
 */
class UserInteractionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_id' => Product::factory(),
            'interaction_type' => fake()->randomElement(['view', 'purchase']),
            'score' => fake()->randomFloat(2, 1, 5),
            'created_at' => now(),
        ];
    }
}
