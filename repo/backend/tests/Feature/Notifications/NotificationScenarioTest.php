<?php

namespace Tests\Feature\Notifications;

use App\Models\RideOrder;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('test-notification-events');
        parent::tearDown();
    }

    public function test_authorized_shared_ride_comment_reply_mention_notifications_are_created(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $rider = User::factory()->create(['role' => 'rider']);
        $ride = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($driver);

        foreach (['comment' => 'order_update', 'reply' => 'reply', 'mention' => 'mention'] as $scenario => $type) {
            $this->postJson('/api/v1/notifications/events', [
                'scenario' => $scenario,
                'recipient_id' => $rider->id,
                'ride_id' => $ride->id,
            ])->assertStatus(201);

            $this->assertDatabaseHas('notifications', [
                'user_id' => $rider->id,
                'type' => $type,
            ]);
        }
    }

    public function test_comment_reply_mention_for_unrelated_ride_are_forbidden(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $rider = User::factory()->create(['role' => 'rider']);
        $otherRider = User::factory()->create(['role' => 'rider']);

        $ride = RideOrder::factory()->create([
            'rider_id' => $otherRider->id,
            'driver_id' => null,
        ]);

        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'reply',
            'recipient_id' => $rider->id,
            'ride_id' => $ride->id,
        ])->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_missing_ride_context_for_comment_reply_mention_is_rejected(): void
    {
        $actor = User::factory()->create(['role' => 'driver']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'comment',
            'recipient_id' => $recipient->id,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_follower_notification_requires_real_follow_relationship(): void
    {
        $actor = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'follower',
            'recipient_id' => $recipient->id,
        ])->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        UserFollow::query()->create([
            'follower_id' => $actor->id,
            'followed_id' => $recipient->id,
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'follower',
            'recipient_id' => $recipient->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'follower',
        ]);
    }

    public function test_follower_notification_cannot_be_spoofed_with_subscription_record(): void
    {
        $actor = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'driver']);

        NotificationSubscription::query()->create([
            'user_id' => $actor->id,
            'entity_type' => 'follow_user',
            'entity_id' => $recipient->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'follower',
            'recipient_id' => $recipient->id,
        ])->assertStatus(403);
    }

    public function test_non_admin_moderation_announcement_are_forbidden_and_admin_allowed(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'moderation',
            'recipient_id' => $recipient->id,
        ])->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'moderation',
            'recipient_id' => $recipient->id,
            'message' => 'Content review completed.',
        ])->assertStatus(201);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'announcement',
            'recipient_id' => $recipient->id,
            'message' => 'System maintenance tonight.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'moderation',
            'priority' => 'high',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'system',
            'priority' => 'high',
        ]);
    }

    public function test_notification_events_endpoint_is_throttled(): void
    {
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $actor = User::factory()->create(['role' => 'admin']);
        $recipient = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($actor);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/notifications/events', [
                'scenario' => 'announcement',
                'recipient_id' => $recipient->id,
                'message' => 'Bulk broadcast',
            ])->assertStatus(201);
        }

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'announcement',
            'recipient_id' => $recipient->id,
            'message' => 'One more',
        ])->assertStatus(429);
    }
}
