<?php

namespace App\Services;

use App\Models\User;

class NotificationScenarioService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(User $actor, User $recipient, string $scenario, array $payload = []): void
    {
        $rideId = isset($payload['ride_id']) ? (int) $payload['ride_id'] : null;
        $entityType = isset($payload['entity_type']) ? (string) $payload['entity_type'] : 'ride_order';
        $entityId = isset($payload['entity_id']) ? (int) $payload['entity_id'] : $rideId;
        $message = isset($payload['message']) ? (string) $payload['message'] : '';

        $route = $rideId ? '/rider/trips/'.$rideId.'/chat' : '/dashboard';

        $definition = match ($scenario) {
            'comment' => [
                'type' => 'order_update',
                'title' => 'New comment',
                'body' => sprintf('@%s commented on your trip thread.', $actor->username),
                'group_key' => $rideId ? 'comment_ride_'.$rideId : 'comment_general',
                'url' => $route,
            ],
            'reply' => [
                'type' => 'reply',
                'title' => 'New reply',
                'body' => sprintf('@%s replied to your message.', $actor->username),
                'group_key' => $rideId ? 'reply_ride_'.$rideId : 'reply_general',
                'url' => $route,
            ],
            'mention' => [
                'type' => 'mention',
                'title' => 'You were mentioned',
                'body' => sprintf('@%s mentioned you in a thread.', $actor->username),
                'group_key' => $rideId ? 'mention_ride_'.$rideId : 'mention_general',
                'url' => $route,
            ],
            'follower' => [
                'type' => 'follower',
                'title' => 'New follower',
                'body' => sprintf('@%s started following your activity.', $actor->username),
                'group_key' => 'follower_user_'.$recipient->id,
                'url' => '/dashboard',
            ],
            'moderation' => [
                'type' => 'moderation',
                'title' => 'Moderation update',
                'body' => $message !== '' ? $message : 'A moderation decision has been posted for your account.',
                'group_key' => 'moderation_user_'.$recipient->id,
                'url' => '/settings/notifications',
            ],
            'announcement' => [
                'type' => 'system',
                'title' => 'System announcement',
                'body' => $message !== '' ? $message : 'A new platform announcement is available.',
                'group_key' => 'announcement_day_'.now()->toDateString(),
                'url' => '/dashboard',
            ],
        };

        $this->notificationService->send(
            $recipient,
            $definition['type'],
            $definition['title'],
            $definition['body'],
            [
                'scenario' => $scenario,
                'actor_id' => $actor->id,
                'actor_username' => $actor->username,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => $message,
                'url' => $definition['url'],
            ],
            $definition['group_key']
        );
    }
}
