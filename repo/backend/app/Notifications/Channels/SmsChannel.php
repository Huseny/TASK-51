<?php

namespace App\Notifications\Channels;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannelInterface
{
    public function deliver(Notification $notification): void
    {
        $user = $notification->user;
        $recipientHash = substr(hash('sha256', (string) $user?->id.'|'.(string) $user?->phone), 0, 12);

        Log::debug(sprintf('Would send SMS notification [recipient=%s]: %s', $recipientHash, $notification->title));
    }
}
