<?php

namespace Database\Factories;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideOrder>
 */
class RideOrderFactory extends Factory
{
    protected $model = RideOrder::class;

    public function definition(): array
    {
        $start = now()->addHour();

        return [
            'rider_id' => User::factory()->state(['role' => 'rider']),
            'driver_id' => null,
            'origin_address' => fake()->streetAddress(),
            'destination_address' => fake()->streetAddress(),
            'rider_count' => 1,
            'time_window_start' => $start,
            'time_window_end' => $start->copy()->addHour(),
            'notes' => null,
            'status' => 'matching',
            'accepted_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
