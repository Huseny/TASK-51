<?php

namespace Tests\Feature\Security;

use App\Models\Notification;
use App\Models\Product;
use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObjectLevelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_cannot_view_another_riders_ride_order(): void
    {
        $riderA = User::factory()->create(['role' => 'rider']);
        $riderB = User::factory()->create(['role' => 'rider']);
        $order = RideOrder::factory()->create(['rider_id' => $riderB->id]);

        Sanctum::actingAs($riderA);

        $this->getJson('/api/v1/ride-orders/'.$order->id)->assertStatus(403);
    }

    public function test_driver_cannot_transition_another_drivers_accepted_ride(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driverA = User::factory()->create(['role' => 'driver']);
        $driverB = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driverB->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($driverA);

        $this->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
            'action' => 'start',
        ])->assertStatus(403);
    }

    public function test_fleet_manager_cannot_update_other_managers_product(): void
    {
        $managerA = User::factory()->create(['role' => 'fleet_manager']);
        $managerB = User::factory()->create(['role' => 'fleet_manager']);

        $product = Product::factory()->create(['seller_id' => $managerB->id]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-OBJ-01',
            'label' => 'Main',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 10,
        ]);
        $variant->pricingTiers()->create([
            'min_quantity' => 1,
            'max_quantity' => null,
            'unit_price' => 20,
        ]);

        Sanctum::actingAs($managerA);

        $this->putJson('/api/v1/products/'.$product->id, [
            'name' => 'Unauthorized update',
            'description' => 'Blocked',
            'category' => 'gear',
            'variants' => [[
                'id' => $variant->id,
                'sku' => 'SKU-OBJ-01',
                'label' => 'Main',
                'inventory_strategy' => 'live_stock',
                'stock_quantity' => 10,
                'tiers' => [[
                    'min_quantity' => 1,
                    'max_quantity' => null,
                    'unit_price' => 20,
                ]],
            ]],
        ])->assertStatus(403);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $userA = User::factory()->create(['role' => 'rider']);
        $userB = User::factory()->create(['role' => 'driver']);

        $notification = Notification::factory()->create([
            'user_id' => $userB->id,
            'type' => 'system',
            'priority' => 'high',
            'title' => 'Notice',
            'body' => 'body',
        ]);

        Sanctum::actingAs($userA);

        $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertStatus(403);
    }
}
