<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;

class InteractionService
{
    public function log(User $user, int $itemId, string $interactionType): void
    {
        $productExists = Product::query()
            ->where('id', $itemId)
            ->where('is_published', true)
            ->exists();

        if (! $productExists) {
            return;
        }

        $score = $interactionType === 'purchase' ? 5.0 : 1.0;

        UserInteraction::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'item_id' => $itemId,
                'interaction_type' => $interactionType,
            ],
            [
                'score' => $score,
                'created_at' => now(),
            ]
        );
    }
}
