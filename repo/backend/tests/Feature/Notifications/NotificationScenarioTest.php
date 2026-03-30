<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_notification_event_is_created(): void
    {
        $actor = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'comment',
            'recipient_id' => $recipient->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'order_update',
        ]);
    }

    public function test_reply_notification_event_is_created(): void
    {
        $actor = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'reply',
            'recipient_id' => $recipient->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'reply',
        ]);
    }

    public function test_mention_notification_event_is_created(): void
    {
        $actor = User::factory()->create(['role' => 'driver']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'mention',
            'recipient_id' => $recipient->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'mention',
        ]);
    }

    public function test_follower_notification_event_is_created(): void
    {
        $actor = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'follower',
            'recipient_id' => $recipient->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'follower',
        ]);
    }

    public function test_only_admin_or_fleet_manager_can_create_moderation_or_announcement_events(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $recipient = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'moderation',
            'recipient_id' => $recipient->id,
        ])->assertStatus(403);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'moderation',
            'recipient_id' => $recipient->id,
            'message' => 'Your content review has been completed.',
        ])->assertStatus(201);

        $this->postJson('/api/v1/notifications/events', [
            'scenario' => 'announcement',
            'recipient_id' => $recipient->id,
            'message' => 'Platform maintenance tonight.',
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
}
