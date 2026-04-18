<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_notifications(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_notifications_list_does_not_include_other_users(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->count(5)->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_notifications_list_supports_pagination(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->count(25)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications?per_page=10&page=1')
            ->assertStatus(200);

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('total'));
        $this->assertSame(10, $response->json('per_page'));
    }

    public function test_notifications_response_has_correct_schema(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->create([
            'user_id' => $user->id,
            'type' => 'reply',
            'priority' => 'normal',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('priority', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('body', $item);
        $this->assertArrayHasKey('is_read', $item);
        $this->assertArrayHasKey('count', $item);
        $this->assertArrayHasKey('notification_ids', $item);
    }

    public function test_grouped_unread_notifications_are_aggregated(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $groupKey = 'reply_post_42';

        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'group_key' => $groupKey,
            'is_read' => false,
        ]);

        Notification::factory()->create([
            'user_id' => $user->id,
            'group_key' => null,
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $grouped = collect($data)->firstWhere('group_key', $groupKey);
        $this->assertNotNull($grouped);
        $this->assertSame(3, $grouped['count']);
        $this->assertCount(3, $grouped['notification_ids']);
        $this->assertStringContainsString('3 new replies', $grouped['title']);
    }

    public function test_unread_count_returns_correct_number(): void
    {
        $user = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(4)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(2)->create(['user_id' => $user->id, 'is_read' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 4);
    }

    public function test_unread_count_excludes_other_users_notifications(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(10)->create(['user_id' => $other->id, 'is_read' => false]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 3);
    }

    public function test_unread_count_is_zero_when_all_read(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Notification marked as read')
            ->assertJsonPath('updated_count', 1);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_marking_grouped_notification_as_read_marks_all_in_group(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $groupKey = 'follower_user_5';

        $notifications = Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'group_key' => $groupKey,
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/'.$notifications->first()->id.'/read')
            ->assertStatus(200)
            ->assertJsonPath('updated_count', 3);

        foreach ($notifications as $n) {
            $this->assertDatabaseHas('notifications', [
                'id' => $n->id,
                'is_read' => true,
            ]);
        }
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);
        $notification = Notification::factory()->create([
            'user_id' => $other->id,
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => false,
        ]);
    }

    public function test_mark_all_read_updates_all_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->count(5)->create(['user_id' => $user->id, 'is_read' => false]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('message', 'All notifications marked as read')
            ->assertJsonPath('updated_count', 5);

        $this->assertSame(0, Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count());
    }

    public function test_mark_all_read_does_not_affect_other_users(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);

        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(4)->create(['user_id' => $other->id, 'is_read' => false]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('updated_count', 3);

        $this->assertSame(4, Notification::query()
            ->where('user_id', $other->id)
            ->where('is_read', false)
            ->count());
    }

    public function test_mark_all_read_returns_zero_when_already_all_read(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => true]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('updated_count', 0);
    }

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertStatus(401);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(401);
    }
}
