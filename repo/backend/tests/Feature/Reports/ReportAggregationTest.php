<?php

namespace Tests\Feature\Reports;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trends_groups_by_day_correctly(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);

        RideOrder::factory()->create(['created_at' => '2026-03-01 10:00:00']);
        RideOrder::factory()->create(['created_at' => '2026-03-01 12:00:00']);
        RideOrder::factory()->create(['created_at' => '2026-03-02 09:00:00']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/reports/trends?grouping=day&start_date=2026-03-01&end_date=2026-03-02')
            ->assertStatus(200)
            ->json();

        $labels = $response['labels'];
        $values = $response['datasets'][0]['data'];

        $this->assertSame(['2026-03-01', '2026-03-02'], $labels);
        $this->assertSame([2, 1], $values);
    }

    public function test_regions_matching_maps_main_st_to_downtown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        RideOrder::factory()->create([
            'origin_address' => '123 Main St, City',
            'created_at' => '2026-03-01 10:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/regions?start_date=2026-03-01&end_date=2026-03-01')
            ->assertStatus(200)
            ->json('data');

        $downtown = collect($response)->firstWhere('region', 'Downtown');
        $this->assertNotNull($downtown);
        $this->assertSame(1, $downtown['total']);
    }
}
