<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\FollowStoreRequest;
use App\Models\UserFollow;
use Illuminate\Http\JsonResponse;

class FollowController extends Controller
{
    public function store(FollowStoreRequest $request): JsonResponse
    {
        $followerId = (int) $request->user()->id;
        $followedId = (int) $request->validated('followed_id');

        if ($followerId === $followedId) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        UserFollow::query()->firstOrCreate([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ], [
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Followed successfully'], 201);
    }
}
