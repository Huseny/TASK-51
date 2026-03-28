<?php

namespace Tests\Feature\Rides;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideOrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_a_cannot_view_rider_b_order(): void
    {
        $riderA = User::factory()->create(['role' => 'rider']);
        $riderB = User::factory()->create(['role' => 'rider']);

        $order = RideOrder::factory()->create(['rider_id' => $riderB->id]);

        Sanctum::actingAs($riderA);

        $this->getJson('/api/v1/ride-orders/'.$order->id)->assertStatus(403);
    }

    public function test_rider_a_cannot_cancel_rider_b_order(): void
    {
        $riderA = User::factory()->create(['role' => 'rider']);
        $riderB = User::factory()->create(['role' => 'rider']);

        $order = RideOrder::factory()->create([
            'rider_id' => $riderB->id,
            'status' => 'matching',
        ]);

        Sanctum::actingAs($riderA);

        $this->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
            'action' => 'cancel',
            'reason' => 'not_my_order',
        ])->assertStatus(403);
    }

    public function test_rider_cannot_cancel_in_progress_order(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'in_progress',
        ]);

        Sanctum::actingAs($rider);

        $this->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
            'action' => 'cancel',
            'reason' => 'cannot_cancel_now',
        ])->assertStatus(403);
    }

    public function test_admin_can_view_any_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rider = User::factory()->create(['role' => 'rider']);
        $order = RideOrder::factory()->create(['rider_id' => $rider->id]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/ride-orders/'.$order->id)
            ->assertStatus(200)
            ->assertJsonPath('order.id', $order->id);
    }
}
