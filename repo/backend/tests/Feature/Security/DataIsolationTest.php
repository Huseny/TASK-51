<?php

namespace Tests\Feature\Security;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ride_index_only_returns_authenticated_riders_orders(): void
    {
        $riderA = User::factory()->create(['role' => 'rider']);
        $riderB = User::factory()->create(['role' => 'rider']);

        RideOrder::factory()->count(2)->create(['rider_id' => $riderA->id]);
        RideOrder::factory()->count(3)->create(['rider_id' => $riderB->id]);

        Sanctum::actingAs($riderA);

        $response = $this->getJson('/api/v1/ride-orders?user_id='.$riderB->id)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $response);

        foreach ($response as $row) {
            $this->assertSame($riderA->id, $row['rider_id']);
        }
    }
}
