<?php

namespace App\Services;

use App\Models\RideOrder;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Facades\Schema;

class NotificationScenarioAuthorizationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function canPublish(User $actor, User $recipient, string $scenario, array $payload): bool
    {
        if (in_array($scenario, ['moderation', 'announcement'], true)) {
            return in_array($actor->role, ['admin', 'fleet_manager'], true);
        }

        if (in_array($scenario, ['comment', 'reply', 'mention'], true)) {
            return $this->hasSharedRideContext($actor, $recipient, $payload);
        }

        if ($scenario === 'follower') {
            if (Schema::hasTable('user_follows')) {
                $hasDirectFollow = UserFollow::query()
                    ->where('follower_id', $actor->id)
                    ->where('followed_id', $recipient->id)
                    ->exists();

                if ($hasDirectFollow) {
                    return true;
                }
            }

            return NotificationSubscription::query()
                ->where('user_id', $actor->id)
                ->where('entity_type', 'follow_user')
                ->where('entity_id', $recipient->id)
                ->exists();
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasSharedRideContext(User $actor, User $recipient, array $payload): bool
    {
        $rideId = isset($payload['ride_id']) ? (int) $payload['ride_id'] : 0;
        if ($rideId <= 0 || $actor->id === $recipient->id) {
            return false;
        }

        $ride = RideOrder::query()->find($rideId);
        if (! $ride) {
            return false;
        }

        $participants = array_values(array_filter([(int) $ride->rider_id, (int) ($ride->driver_id ?? 0)]));

        return in_array($actor->id, $participants, true)
            && in_array($recipient->id, $participants, true);
    }
}
