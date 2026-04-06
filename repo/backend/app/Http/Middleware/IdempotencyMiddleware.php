<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function __construct(private readonly IdempotencyService $idempotencyService)
    {
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if (! $this->idempotencyService->shouldHandle($request)) {
            return $next($request);
        }

        $reservation = $this->idempotencyService->begin($request);
        $request->attributes->set('idempotency_context', $reservation['context']);

        if ($reservation['type'] !== 'created') {
            return $reservation['response'];
        }

        $response = $next($request);

        $this->idempotencyService->storeResponse($reservation['record'], $response);

        return $response;
    }
}
