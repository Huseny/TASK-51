<?php

namespace Tests\Feature\Rides;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_creates_order_with_valid_data(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $response = $this->postJson('/api/v1/ride-orders', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('order.status', 'matching')
            ->assertJsonCount(2, 'order.audit_logs');
    }

    public function test_rider_count_zero_returns_422(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = $this->payload();
        $payload['rider_count'] = 0;

        $this->postJson('/api/v1/ride-orders', $payload)->assertStatus(422);
    }

    public function test_rider_count_seven_returns_422(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = $this->payload();
        $payload['rider_count'] = 7;

        $this->postJson('/api/v1/ride-orders', $payload)->assertStatus(422);
    }

    public function test_time_window_in_past_returns_422(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = $this->payload();
        $payload['time_window_start'] = now()->subHour()->format('Y-m-d H:i');

        $this->postJson('/api/v1/ride-orders', $payload)->assertStatus(422);
    }

    public function test_end_before_start_returns_422(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = $this->payload();
        $payload['time_window_end'] = now()->addMinutes(30)->format('Y-m-d H:i');

        $this->postJson('/api/v1/ride-orders', $payload)->assertStatus(422);
    }

    public function test_empty_origin_returns_422(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = $this->payload();
        $payload['origin_address'] = '';

        $this->postJson('/api/v1/ride-orders', $payload)->assertStatus(422);
    }

    public function test_driver_cannot_create_ride_order(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/ride-orders', $this->payload())->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_ride_order(): void
    {
        $this->postJson('/api/v1/ride-orders', $this->payload())->assertStatus(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'origin_address' => '123 Main St, Suite 4, Springfield',
            'destination_address' => '456 Oak Ave, Downtown Mall',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'Two cabin bags',
        ];
    }
}
