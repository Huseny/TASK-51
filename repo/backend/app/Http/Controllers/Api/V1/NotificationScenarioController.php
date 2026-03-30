<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\NotificationScenarioRequest;
use App\Models\User;
use App\Services\NotificationScenarioAuthorizationService;
use App\Services\NotificationScenarioService;
use Illuminate\Http\JsonResponse;

class NotificationScenarioController extends Controller
{
    public function __construct(
        private readonly NotificationScenarioService $scenarioService,
        private readonly NotificationScenarioAuthorizationService $authorizationService,
    )
    {
    }

    public function store(NotificationScenarioRequest $request): JsonResponse
    {
        $actor = $request->user();
        $payload = $request->validated();
        $scenario = (string) $payload['scenario'];

        $recipient = User::query()->findOrFail((int) $payload['recipient_id']);

        if (! $this->authorizationService->canPublish($actor, $recipient, $scenario, $payload)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Actor is not authorized to publish this notification scenario for the selected recipient/resource.',
            ], 403);
        }

        $this->scenarioService->publish($actor, $recipient, $scenario, $payload);

        return response()->json(['message' => 'Notification event published'], 201);
    }
}
