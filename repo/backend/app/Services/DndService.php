<?php

namespace App\Services;

use App\Models\GroupChat;
use App\Models\User;
use Illuminate\Support\Carbon;

class DndService
{
    public function isInDndWindow(User $user, GroupChat $chat): bool
    {
        $participant = $chat->participants()
            ->where('user_id', $user->id)
            ->active()
            ->first();

        if (! $participant) {
            return false;
        }

        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        $start = $participant->dnd_start;
        $end = $participant->dnd_end;

        if ($start === $end) {
            return false;
        }

        if ($start < $end) {
            return $currentTime >= $start && $currentTime < $end;
        }

        return $currentTime >= $start || $currentTime < $end;
    }
}
