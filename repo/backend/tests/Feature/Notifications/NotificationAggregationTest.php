<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_notifications_with_same_group_key_are_aggregated(): void
    {
        $user = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'type' => 'ride_update',
            'priority' => 'normal',
            'title' => 'Ride update',
            'body' => 'Driver is near',
            'group_key' => 'ride_123_updates',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.count', 5)
            ->assertJsonPath('data.0.group_key', 'ride_123_updates')
            ->assertJsonPath('data.0.title', '5 new updates on Ride #123');
    }

    public function test_reply_group_uses_reply_aggregate_copy(): void
    {
        $user = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'type' => 'reply',
            'priority' => 'normal',
            'title' => 'Reply update',
            'body' => 'Someone replied',
            'group_key' => 'reply_ride_99',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.count', 5)
            ->assertJsonPath('data.0.title', '5 new replies');
    }
}
