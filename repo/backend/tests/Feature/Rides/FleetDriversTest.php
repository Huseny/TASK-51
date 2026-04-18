<?php

namespace Tests\Feature\Rides;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetDriversTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_list_drivers(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $drivers = User::factory()->count(3)->create(['role' => 'driver']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_list_drivers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'driver']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_drivers_list_excludes_non_driver_roles(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        User::factory()->count(2)->create(['role' => 'rider']);
        User::factory()->count(3)->create(['role' => 'driver']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $driver) {
            $this->assertArrayHasKey('id', $driver);
            $this->assertArrayHasKey('username', $driver);
        }
    }

    public function test_drivers_list_is_ordered_by_username(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        User::factory()->create(['role' => 'driver', 'username' => 'zebra_driver']);
        User::factory()->create(['role' => 'driver', 'username' => 'alpha_driver']);
        User::factory()->create(['role' => 'driver', 'username' => 'middle_driver']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(200);

        $usernames = array_column($response->json('data'), 'username');
        $this->assertSame(['alpha_driver', 'middle_driver', 'zebra_driver'], $usernames);
    }

    public function test_rider_cannot_access_fleet_drivers(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_driver_cannot_access_fleet_drivers(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_fleet_drivers(): void
    {
        $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_empty_driver_list_returns_empty_data(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/drivers')
            ->assertStatus(200);

        $this->assertEmpty($response->json('data'));
    }
}
