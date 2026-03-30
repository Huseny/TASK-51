<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\NotificationScenarioRequest;
use App\Models\User;
use App\Services\NotificationScenarioService;
use Illuminate\Http\JsonResponse;

class NotificationScenarioController extends Controller
{
    public function __construct(private readonly NotificationScenarioService $scenarioService)
    {
    }

    public function store(NotificationScenarioRequest $request): JsonResponse
    {
        $actor = $request->user();
        $payload = $request->validated();
        $scenario = (string) $payload['scenario'];

        if (in_array($scenario, ['moderation', 'announcement'], true)
            && ! in_array($actor->role, ['admin', 'fleet_manager'], true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Only moderators can create moderation or announcement notifications.',
            ], 403);
        }

        $recipient = User::query()->findOrFail((int) $payload['recipient_id']);

        $this->scenarioService->publish($actor, $recipient, $scenario, $payload);

        return response()->json(['message' => 'Notification event published'], 201);
    }
}
