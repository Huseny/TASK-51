<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Ford']),
            'model' => fake()->randomElement(['Sedan', 'SUV', 'Hatch']),
            'year' => fake()->numberBetween(2000, 2028),
            'license_plate' => strtoupper(fake()->bothify('??###??')),
            'color' => fake()->safeColorName(),
            'capacity' => fake()->numberBetween(2, 8),
            'status' => 'active',
        ];
    }
}
