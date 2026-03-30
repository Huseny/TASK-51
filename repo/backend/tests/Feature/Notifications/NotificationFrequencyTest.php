<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationFrequencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_21_normal_notifications_of_same_type_only_store_20(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 09:00:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 21; $i++) {
            $service->send($user, 'reply', 'Reply update', 'You received a reply', [
                'entity_type' => 'ride_order',
                'entity_id' => 10,
            ]);
        }

        $this->assertDatabaseCount('notifications', 20);
        $this->assertDatabaseCount('notification_frequency_logs', 20);
        $this->assertDatabaseHas('notification_frequency_logs', ['priority' => 'normal', 'type' => 'reply']);
    }

    public function test_normal_priority_cap_is_scoped_per_notification_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 09:45:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 20; $i++) {
            $service->send($user, 'reply', 'Reply update', 'You received a reply');
            $service->send($user, 'mention', 'Mention update', 'You were mentioned');
        }

        $service->send($user, 'reply', 'Reply overflow', 'Suppressed reply');
        $service->send($user, 'mention', 'Mention overflow', 'Suppressed mention');

        $this->assertDatabaseCount('notifications', 40);
        $this->assertDatabaseCount('notification_frequency_logs', 40);

        $this->assertSame(
            20,
            Notification::query()->where('user_id', $user->id)->where('type', 'reply')->count()
        );
        $this->assertSame(
            20,
            Notification::query()->where('user_id', $user->id)->where('type', 'mention')->count()
        );
    }

    public function test_4_high_notifications_within_hour_only_store_3(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 10:15:00'));
        $user = User::factory()->create(['role' => 'driver']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 4; $i++) {
            $service->send($user, 'system', 'System alert', 'Important system notice');
        }

        $this->assertDatabaseCount('notifications', 3);
        $this->assertDatabaseCount('notification_frequency_logs', 3);
    }

    public function test_high_and_normal_caps_do_not_interfere(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 11:00:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 20; $i++) {
            $service->send($user, 'reply', 'Reply', 'Normal notification');
        }

        for ($i = 0; $i < 3; $i++) {
            $service->send($user, 'moderation', 'Moderation', 'High notification');
        }

        $service->send($user, 'reply', 'Reply overflow', 'Suppressed normal');
        $service->send($user, 'moderation', 'High overflow', 'Suppressed high');

        $this->assertDatabaseCount('notifications', 23);
        $this->assertDatabaseHas('notification_frequency_logs', ['priority' => 'normal', 'type' => 'reply']);
        $this->assertDatabaseHas('notification_frequency_logs', ['priority' => 'high']);
    }

    public function test_normal_cap_applies_to_comment_type_independently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 12:15:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 21; $i++) {
            $service->send($user, 'order_update', 'New comment', 'Comment update');
        }

        for ($i = 0; $i < 20; $i++) {
            $service->send($user, 'follower', 'New follower', 'Follower update');
        }

        $this->assertSame(20, Notification::query()->where('user_id', $user->id)->where('type', 'order_update')->count());
        $this->assertSame(20, Notification::query()->where('user_id', $user->id)->where('type', 'follower')->count());
    }
}
