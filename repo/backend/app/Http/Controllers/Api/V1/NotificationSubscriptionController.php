<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\NotificationSubscriptionRequest;
use App\Models\NotificationSubscription;
use App\Models\Product;
use App\Models\RideOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptions = NotificationSubscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $subscriptions]);
    }

    public function store(NotificationSubscriptionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $user = $request->user();

        if (! $this->canSubscribeToEntity($user->id, $payload['entity_type'], (int) $payload['entity_id'])) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to subscribe to this entity.',
            ], 403);
        }

        $subscription = NotificationSubscription::query()->firstOrCreate([
            'user_id' => $user->id,
            'entity_type' => $payload['entity_type'],
            'entity_id' => $payload['entity_id'],
        ], [
            'created_at' => now(),
        ]);

        return response()->json(['subscription' => $subscription], 201);
    }

    public function destroy(Request $request, NotificationSubscription $notificationSubscription): JsonResponse
    {
        if ($notificationSubscription->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to remove this subscription',
            ], 403);
        }

        $notificationSubscription->delete();

        return response()->json(['message' => 'Subscription removed']);
    }

    private function canSubscribeToEntity(int $userId, string $entityType, int $entityId): bool
    {
        return match ($entityType) {
            'ride_order' => RideOrder::query()
                ->whereKey($entityId)
                ->where(function ($query) use ($userId): void {
                    $query->where('rider_id', $userId)
                        ->orWhere('driver_id', $userId);
                })
                ->exists(),
            'product' => Product::query()
                ->whereKey($entityId)
                ->where(function ($query) use ($userId): void {
                    $query->where('is_published', true)
                        ->orWhere('seller_id', $userId);
                })
                ->exists(),
            default => false,
        };
    }
}
