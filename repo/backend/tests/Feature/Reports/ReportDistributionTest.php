<?php

namespace Tests\Feature\Reports;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_access_distribution(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(200);
    }

    public function test_admin_can_access_distribution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(200);
    }

    public function test_distribution_returns_correct_structure(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(200);

        $this->assertArrayHasKey('labels', $response->json());
        $this->assertArrayHasKey('datasets', $response->json());
    }

    public function test_distribution_aggregates_ride_statuses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        RideOrder::factory()->create(['status' => 'completed', 'created_at' => '2026-04-01 10:00:00']);
        RideOrder::factory()->create(['status' => 'completed', 'created_at' => '2026-04-01 11:00:00']);
        RideOrder::factory()->create(['status' => 'cancelled', 'created_at' => '2026-04-01 12:00:00']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/distribution?start_date=2026-04-01&end_date=2026-04-01')
            ->assertStatus(200)
            ->json();

        $labels = $response['labels'];
        $data = $response['datasets'][0]['data'];

        $completedIndex = array_search('completed', $labels, true);
        $cancelledIndex = array_search('cancelled', $labels, true);

        $this->assertNotFalse($completedIndex);
        $this->assertNotFalse($cancelledIndex);
        $this->assertSame(2, $data[$completedIndex]);
        $this->assertSame(1, $data[$cancelledIndex]);
    }

    public function test_rider_cannot_access_distribution(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_driver_cannot_access_distribution(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_distribution(): void
    {
        $this->getJson('/api/v1/reports/distribution')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_distribution_with_date_filter_only_includes_matching_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        RideOrder::factory()->create(['status' => 'completed', 'created_at' => '2026-01-15 10:00:00']);
        RideOrder::factory()->create(['status' => 'cancelled', 'created_at' => '2026-02-20 10:00:00']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/distribution?start_date=2026-01-01&end_date=2026-01-31')
            ->assertStatus(200)
            ->json();

        $labels = $response['labels'];
        $data = $response['datasets'][0]['data'];

        $completedIndex = array_search('completed', $labels, true);
        $this->assertNotFalse($completedIndex, 'Completed should appear in January results');
        $this->assertSame(1, $data[$completedIndex]);

        $cancelledIndex = array_search('cancelled', $labels, true);
        $this->assertFalse($cancelledIndex, 'Cancelled from February should not appear in January results');
    }
}
