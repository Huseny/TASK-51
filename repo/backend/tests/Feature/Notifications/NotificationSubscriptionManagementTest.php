<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSubscription;
use App\Models\Product;
use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_subscriptions(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        NotificationSubscription::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notification-subscriptions')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_subscriptions_list_excludes_other_users(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);

        NotificationSubscription::factory()->count(2)->create(['user_id' => $user->id]);
        NotificationSubscription::factory()->count(5)->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notification-subscriptions')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_empty_subscriptions_list_returns_empty_data(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notification-subscriptions')
            ->assertStatus(200);

        $this->assertEmpty($response->json('data'));
    }

    public function test_subscriptions_list_has_correct_schema(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_published' => true]);

        NotificationSubscription::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'product',
            'entity_id' => $product->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notification-subscriptions')
            ->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('user_id', $item);
        $this->assertArrayHasKey('entity_type', $item);
        $this->assertArrayHasKey('entity_id', $item);
    }

    public function test_user_can_delete_own_subscription(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $subscription = NotificationSubscription::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/notification-subscriptions/'.$subscription->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Subscription removed');

        $this->assertDatabaseMissing('notification_subscriptions', ['id' => $subscription->id]);
    }

    public function test_user_cannot_delete_other_users_subscription(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);
        $subscription = NotificationSubscription::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/notification-subscriptions/'.$subscription->id)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->assertDatabaseHas('notification_subscriptions', ['id' => $subscription->id]);
    }

    public function test_delete_nonexistent_subscription_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/notification-subscriptions/99999')
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_list_subscriptions(): void
    {
        $this->getJson('/api/v1/notification-subscriptions')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_unauthenticated_cannot_delete_subscription(): void
    {
        $subscription = NotificationSubscription::factory()->create();

        $this->deleteJson('/api/v1/notification-subscriptions/'.$subscription->id)
            ->assertStatus(401);
    }

    public function test_rider_can_subscribe_to_own_ride_order_and_then_delete_it(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create(['rider_id' => $rider->id]);

        Sanctum::actingAs($rider);

        $response = $this->postJson('/api/v1/notification-subscriptions', [
            'entity_type' => 'ride_order',
            'entity_id' => $ride->id,
        ])->assertStatus(201);

        $subscriptionId = $response->json('subscription.id');

        $this->deleteJson('/api/v1/notification-subscriptions/'.$subscriptionId)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Subscription removed');

        $this->assertDatabaseMissing('notification_subscriptions', ['id' => $subscriptionId]);
    }
}
