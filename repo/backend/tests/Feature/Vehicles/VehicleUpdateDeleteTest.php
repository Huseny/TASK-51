<?php

namespace Tests\Feature\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleUpdateDeleteTest extends TestCase
{
    use RefreshDatabase;

    // ── PUT /api/v1/vehicles/{vehicle} ────────────────────────────────────────

    public function test_owner_can_update_own_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'license_plate' => 'XYZ-999',
            'color' => 'Red',
            'capacity' => 5,
        ])
            ->assertStatus(200)
            ->assertJsonPath('vehicle.make', 'Honda')
            ->assertJsonPath('vehicle.model', 'Civic')
            ->assertJsonPath('vehicle.year', 2023)
            ->assertJsonPath('vehicle.license_plate', 'XYZ-999')
            ->assertJsonStructure([
                'vehicle' => ['id', 'make', 'model', 'year', 'license_plate', 'color', 'capacity', 'status'],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'make' => 'Honda',
            'model' => 'Civic',
        ]);
    }

    public function test_update_returns_422_when_make_is_missing(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            // 'make' required but omitted
            'model' => 'Civic',
            'year' => 2023,
            'license_plate' => 'XYZ-999',
        ])->assertStatus(422);
    }

    public function test_update_returns_422_when_year_is_out_of_range(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 1800, // valid range is 1990–2030
            'license_plate' => 'XYZ-999',
        ])->assertStatus(422);
    }

    public function test_update_returns_422_when_status_is_invalid(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2022,
            'license_plate' => 'XYZ-999',
            'status' => 'broken', // not in [active, inactive]
        ])->assertStatus(422);
    }

    public function test_non_owner_cannot_update_vehicle(): void
    {
        $owner = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'license_plate' => 'XYZ-999',
        ])->assertStatus(403);

        // Original data unchanged
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'make' => $vehicle->make]);
    }

    public function test_admin_can_update_any_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Ford',
            'model' => 'Focus',
            'year' => 2021,
            'license_plate' => 'ADMIN-01',
        ])
            ->assertStatus(200)
            ->assertJsonPath('vehicle.make', 'Ford')
            ->assertJsonPath('vehicle.license_plate', 'ADMIN-01');
    }

    public function test_unauthenticated_cannot_update_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'license_plate' => 'XYZ-999',
        ])->assertStatus(401);
    }

    // ── DELETE /api/v1/vehicles/{vehicle} ─────────────────────────────────────

    public function test_owner_can_delete_own_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Vehicle deleted');

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_non_owner_cannot_delete_vehicle(): void
    {
        $owner = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertStatus(403);

        $this->assertNotSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_delete_returns_404_for_nonexistent_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->deleteJson('/api/v1/vehicles/99999')->assertStatus(404);
    }

    public function test_admin_can_delete_any_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Vehicle deleted');

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_unauthenticated_cannot_delete_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertStatus(401);
    }
}
