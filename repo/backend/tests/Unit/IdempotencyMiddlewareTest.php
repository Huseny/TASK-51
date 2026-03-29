<?php

namespace Tests\Unit;

use App\Http\Middleware\IdempotencyMiddleware;
use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_request_without_mutation_bypasses_idempotency_store(): void
    {
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/api/v1/ride-orders', 'GET');
        $request->headers->set('X-Idempotency-Key', 'GET-KEY-1');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true], 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_existing_processed_key_replays_stored_response(): void
    {
        IdempotencyKey::query()->create([
            'key' => 'REPLAY-1',
            'request_path' => 'api/v1/ride-orders',
            'request_method' => 'POST',
            'response_code' => 201,
            'response_body' => ['order' => ['id' => 55]],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/api/v1/ride-orders', 'POST');
        $request->headers->set('X-Idempotency-Key', 'REPLAY-1');

        $response = $middleware->handle($request, fn () => new JsonResponse(['order' => ['id' => 99]], 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(55, $response->getData(true)['order']['id']);
    }
}
