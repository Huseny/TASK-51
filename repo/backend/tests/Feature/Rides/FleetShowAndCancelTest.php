<?php

namespace Tests\Feature\Rides;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetShowAndCancelTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/fleet/rides/{rideOrder} ───────────────────────────────────

    public function test_fleet_manager_can_view_ride_detail(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($fleet);

        $response = $this->getJson('/api/v1/fleet/rides/'.$ride->id)
            ->assertStatus(200)
            ->assertJsonPath('order.id', $ride->id)
            ->assertJsonPath('order.status', 'accepted')
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'status',
                    'origin_address',
                    'destination_address',
                    'audit_logs',
                    'rider',
                    'driver',
                ],
            ]);

        $this->assertSame($driver->id, $response->json('order.driver.id'));
        $this->assertSame($rider->id, $response->json('order.rider.id'));
    }

    public function test_admin_can_view_fleet_ride_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/fleet/rides/'.$ride->id)
            ->assertStatus(200)
            ->assertJsonPath('order.id', $ride->id);
    }

    public function test_fleet_show_response_includes_audit_logs_array(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($fleet);

        $response = $this->getJson('/api/v1/fleet/rides/'.$ride->id)
            ->assertStatus(200);

        $this->assertArrayHasKey('audit_logs', $response->json('order'));
        $this->assertIsArray($response->json('order.audit_logs'));
    }

    public function test_rider_cannot_view_ride_via_fleet_show(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/fleet/rides/'.$ride->id)->assertStatus(403);
    }

    public function test_driver_cannot_view_ride_via_fleet_show(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/fleet/rides/'.$ride->id)->assertStatus(403);
    }

    public function test_fleet_show_returns_404_for_nonexistent_ride(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($fleet);

        $this->getJson('/api/v1/fleet/rides/99999')->assertStatus(404);
    }

    public function test_unauthenticated_cannot_view_fleet_ride(): void
    {
        $ride = RideOrder::factory()->create();

        $this->getJson('/api/v1/fleet/rides/'.$ride->id)
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    // ── PATCH /api/v1/fleet/rides/{rideOrder}/cancel ──────────────────────────

    public function test_fleet_manager_can_cancel_a_matching_ride(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($fleet);

        $response = $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel', [
            'reason' => 'no_drivers_available',
        ])->assertStatus(200);

        $response
            ->assertJsonPath('order.status', 'canceled')
            ->assertJsonStructure([
                'order' => ['id', 'status', 'audit_logs'],
            ]);

        $this->assertSame('canceled', $ride->fresh()->status);
    }

    public function test_fleet_manager_can_cancel_an_accepted_ride(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'status' => 'accepted',
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('order.status', 'canceled');
    }

    public function test_cancel_records_audit_log_entry_with_correct_to_status(): void
    {
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($fleet);

        $response = $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel', [
            'reason' => 'operational_constraint',
        ])->assertStatus(200);

        $auditLogs = $response->json('order.audit_logs');
        $this->assertNotEmpty($auditLogs);
        $latest = end($auditLogs);
        $this->assertSame('canceled', $latest['to_status']);
    }

    public function test_cannot_cancel_already_completed_ride(): void
    {
        // Policy: fleetManage returns false for completed rides → 403
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $ride = RideOrder::factory()->create(['status' => 'completed']);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(403);
    }

    public function test_cannot_cancel_already_canceled_ride(): void
    {
        // Policy: fleetManage returns false for canceled rides → 403
        $fleet = User::factory()->create(['role' => 'fleet_manager']);
        $ride = RideOrder::factory()->create(['status' => 'canceled']);

        Sanctum::actingAs($fleet);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(403);
    }

    public function test_rider_cannot_cancel_ride_via_fleet_endpoint(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($rider);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(403);
    }

    public function test_driver_cannot_cancel_ride_via_fleet_endpoint(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_cancel_ride(): void
    {
        $ride = RideOrder::factory()->create(['status' => 'matching']);

        $this->patchJson('/api/v1/fleet/rides/'.$ride->id.'/cancel')
            ->assertStatus(401);
    }
}
