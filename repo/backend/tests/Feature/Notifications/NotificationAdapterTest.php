<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotificationAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_channel_disabled_by_default_does_not_log_email_delivery(): void
    {
        config()->set('roadlink.channels', ['in_app']);

        $user = User::factory()->create(['role' => 'rider', 'email' => 'notify@example.com']);

        Log::spy();
        app(NotificationService::class)->send($user, 'reply', 'Reply', 'You got a reply');

        Log::shouldNotHaveReceived('debug');
    }

    public function test_email_channel_logs_when_enabled(): void
    {
        config()->set('roadlink.channels', ['in_app', 'email']);

        $user = User::factory()->create(['role' => 'rider', 'email' => 'notify@example.com']);

        Log::spy();
        app(NotificationService::class)->send($user, 'reply', 'Reply', 'You got a reply');

        Log::shouldHaveReceived('debug')->once();
        Log::shouldHaveReceived('debug')->withArgs(function ($message): bool {
            return is_string($message)
                && ! str_contains($message, 'notify@example.com')
                && str_contains($message, 'recipient=');
        });
    }

    public function test_sms_channel_does_not_deliver_when_disabled_even_if_listed(): void
    {
        config()->set('roadlink.channels', ['in_app', 'sms']);
        config()->set('roadlink.sms.enabled', false);

        $user = User::factory()->create(['role' => 'rider', 'email' => 'notify@example.com']);

        Log::spy();
        app(NotificationService::class)->send($user, 'reply', 'Reply', 'You got a reply');

        Log::shouldNotHaveReceived('debug');
    }

    public function test_sms_channel_logs_when_enabled(): void
    {
        config()->set('roadlink.channels', ['in_app', 'sms']);
        config()->set('roadlink.sms.enabled', true);

        $user = User::factory()->create(['role' => 'rider', 'email' => 'notify@example.com', 'phone' => '+15551234567']);

        Log::spy();
        app(NotificationService::class)->send($user, 'reply', 'Reply', 'You got a reply');

        Log::shouldHaveReceived('debug')->once();
        Log::shouldHaveReceived('debug')->withArgs(function ($message): bool {
            return is_string($message)
                && ! str_contains($message, '+15551234567')
                && str_contains($message, 'recipient=');
        });
    }
}
