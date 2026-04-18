<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/panel')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Admin panel access granted');
    }

    public function test_unauthenticated_cannot_access_admin_panel(): void
    {
        $this->getJson('/api/v1/admin/panel')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_rider_cannot_access_admin_panel(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/admin/panel')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_driver_cannot_access_admin_panel(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/admin/panel')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_fleet_manager_cannot_access_admin_panel(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/panel')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_driver_can_access_driver_queue(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/driver/queue')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Driver queue access granted');
    }

    public function test_admin_can_access_driver_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/driver/queue')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Driver queue access granted');
    }

    public function test_rider_cannot_access_driver_queue(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/driver/queue')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_fleet_manager_cannot_access_driver_queue(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/driver/queue')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_unauthenticated_cannot_access_driver_queue(): void
    {
        $this->getJson('/api/v1/driver/queue')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }
}
