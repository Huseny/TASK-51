<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendations\InteractionStoreRequest;
use App\Services\InteractionService;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function __construct(private readonly InteractionService $interactionService)
    {
    }

    public function store(InteractionStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $this->interactionService->log(
            $request->user(),
            (int) $payload['item_id'],
            (string) $payload['interaction_type'],
        );

        return response()->json(['message' => 'Interaction logged'], 201);
    }
}
