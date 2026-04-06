<?php

namespace Tests\Feature\Rides;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetRideManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_view_queue_and_active_rides(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $driver = User::factory()->create(['role' => 'driver']);
        RideOrder::factory()->create(['status' => 'matching']);
        RideOrder::factory()->create([
            'status' => 'accepted',
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($fleet);

        $this->getJson('/api/v1/fleet/rides/queue')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/fleet/rides/active')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_fleet_manager_can_assign_driver_to_matching_ride(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/assign', [
            'driver_id' => $driver->id,
            'reason' => 'dispatch_assignment',
        ])->assertStatus(200)
            ->assertJsonPath('order.driver_id', $driver->id)
            ->assertJsonPath('order.status', 'accepted');
    }

    public function test_fleet_manager_can_reassign_ride_to_new_driver(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $oldDriver = User::factory()->create(['role' => 'driver']);
        $newDriver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'status' => 'accepted',
            'driver_id' => $oldDriver->id,
            'accepted_at' => now()->subMinutes(2),
        ]);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/reassign', [
            'driver_id' => $newDriver->id,
            'reason' => 'manual_reassignment',
        ])->assertStatus(200)
            ->assertJsonPath('order.driver_id', $newDriver->id)
            ->assertJsonPath('order.status', 'accepted');
    }

    public function test_fleet_manager_cannot_assign_overlapping_driver(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $driver = User::factory()->create(['role' => 'driver']);

        RideOrder::factory()->create([
            'status' => 'accepted',
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinute(),
            'time_window_start' => now()->addHour(),
            'time_window_end' => now()->addHours(3),
        ]);

        $target = RideOrder::factory()->create([
            'status' => 'matching',
            'time_window_start' => now()->addHours(2),
            'time_window_end' => now()->addHours(4),
        ]);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$target->id.'/assign', [
            'driver_id' => $driver->id,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'schedule_conflict');
    }

    public function test_driver_cannot_access_fleet_management_routes(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/fleet/rides/queue')->assertStatus(403);
    }
}
