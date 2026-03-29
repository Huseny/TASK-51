<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationCapsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_normal_priority_cap_is_20_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 09:00:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 21; $i++) {
            $service->send($user, 'reply', 'Reply', 'body');
        }

        $this->assertDatabaseCount('notifications', 20);
    }

    public function test_high_priority_cap_is_3_per_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));
        $user = User::factory()->create(['role' => 'rider']);
        $service = app(NotificationService::class);

        for ($i = 0; $i < 4; $i++) {
            $service->send($user, 'system', 'System', 'body');
        }

        $this->assertDatabaseCount('notifications', 3);
    }
}
