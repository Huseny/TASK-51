<?php

namespace Tests\Feature\Rides;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverMyRidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_view_own_ride_detail(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(200)
            ->assertJsonPath('order.id', $ride->id)
            ->assertJsonStructure(['order' => ['id', 'status', 'origin_address', 'destination_address']]);
    }

    public function test_show_my_ride_response_includes_audit_logs_and_relations(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'driver_id' => $driver->id,
            'rider_id' => $rider->id,
            'status' => 'in_progress',
            'accepted_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($driver);

        $response = $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(200);

        $order = $response->json('order');
        $this->assertArrayHasKey('audit_logs', $order);
        $this->assertArrayHasKey('rider', $order);
        $this->assertArrayHasKey('driver', $order);
        $this->assertSame($driver->id, $order['driver']['id']);
        $this->assertSame($rider->id, $order['rider']['id']);
    }

    public function test_driver_cannot_view_another_drivers_ride(): void
    {
        $driverA = User::factory()->create(['role' => 'driver']);
        $driverB = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create([
            'driver_id' => $driverA->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($driverB);

        $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(403);
    }

    public function test_admin_can_view_any_drivers_ride(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ride = RideOrder::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(200)
            ->assertJsonPath('order.id', $ride->id);
    }

    public function test_rider_cannot_view_ride_via_driver_endpoint(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create(['rider_id' => $rider->id]);

        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(403);
    }

    public function test_returns_404_for_nonexistent_ride(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/driver/my-rides/99999')
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_view_my_ride(): void
    {
        $ride = RideOrder::factory()->create();

        $this->getJson('/api/v1/driver/my-rides/'.$ride->id)
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_driver_can_list_own_rides(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        RideOrder::factory()->count(3)->create([
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);
        RideOrder::factory()->create([
            'status' => 'matching',
            'driver_id' => null,
        ]);

        Sanctum::actingAs($driver);

        $response = $this->getJson('/api/v1/driver/my-rides')
            ->assertStatus(200);

        $this->assertSame(3, $response->json('total'));
    }

    public function test_driver_my_rides_can_be_filtered_by_status(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        RideOrder::factory()->count(2)->create([
            'driver_id' => $driver->id,
            'status' => 'completed',
        ]);
        RideOrder::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($driver);

        $response = $this->getJson('/api/v1/driver/my-rides?status=completed')
            ->assertStatus(200);

        $this->assertSame(2, $response->json('total'));

        foreach ($response->json('data') as $ride) {
            $this->assertSame('completed', $ride['status']);
        }
    }

    public function test_unauthenticated_cannot_list_my_rides(): void
    {
        $this->getJson('/api/v1/driver/my-rides')
            ->assertStatus(401);
    }
}
