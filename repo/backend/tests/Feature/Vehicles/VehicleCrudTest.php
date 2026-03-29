<?php

namespace Tests\Feature\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_vehicle_returns_201(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/vehicles', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('vehicle.owner_id', $driver->id);
    }

    public function test_list_own_vehicles_only(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        Vehicle::factory()->create(['owner_id' => $driver->id]);
        Vehicle::factory()->create(['owner_id' => $other->id]);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/vehicles')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_another_users_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $other->id]);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)->assertStatus(403);
    }

    public function test_admin_can_access_any_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertStatus(200)
            ->assertJsonPath('vehicle.id', $vehicle->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'license_plate' => 'ABC123',
            'color' => 'Blue',
            'capacity' => 4,
        ];
    }
}
