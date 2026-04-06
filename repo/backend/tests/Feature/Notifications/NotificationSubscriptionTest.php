<?php

namespace Tests\Feature\Notifications;

use App\Models\Product;
use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_follow_user_subscription(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notification-subscriptions', [
            'entity_type' => 'follow_user',
            'entity_id' => 99,
        ])->assertStatus(422);
    }

    public function test_user_can_only_subscribe_to_owned_ride_order(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $otherRider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create(['rider_id' => $otherRider->id]);

        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/notification-subscriptions', [
            'entity_type' => 'ride_order',
            'entity_id' => $ride->id,
        ])->assertStatus(403);
    }

    public function test_user_can_subscribe_to_published_product(): void
    {
        $buyer = User::factory()->create(['role' => 'rider']);
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_published' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/notification-subscriptions', [
            'entity_type' => 'product',
            'entity_id' => $product->id,
        ])->assertStatus(201);
    }
}
