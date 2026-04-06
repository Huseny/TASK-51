<?php

namespace Tests\Unit;

use App\Http\Middleware\IdempotencyMiddleware;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_request_without_mutation_bypasses_idempotency_store(): void
    {
        $middleware = app(IdempotencyMiddleware::class);
        $request = Request::create('/api/v1/ride-orders', 'GET');
        $request->headers->set('X-Idempotency-Key', 'GET-KEY-1');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true], 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_existing_processed_key_replays_stored_response(): void
    {
        $user = User::factory()->create();

        IdempotencyKey::query()->create([
            'user_id' => $user->id,
            'actor_identifier' => 'user:'.$user->id,
            'key' => 'REPLAY-1',
            'request_path' => 'api/v1/ride-orders',
            'request_method' => 'POST',
            'canonical_path' => 'api/v1/ride-orders',
            'request_hash' => hash('sha256', json_encode([
                'body' => ['notes' => 'same'],
                'query' => [],
            ], JSON_THROW_ON_ERROR)),
            'response_code' => 201,
            'response_body' => ['order' => ['id' => 55]],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = app(IdempotencyMiddleware::class);
        $request = Request::create('/api/v1/ride-orders', 'POST');
        $request->headers->set('X-Idempotency-Key', 'REPLAY-1');
        $request->setUserResolver(fn () => $user);
        $request->request->set('notes', 'same');

        $response = $middleware->handle($request, fn () => new JsonResponse(['order' => ['id' => 99]], 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(55, $response->getData(true)['order']['id']);
    }

    public function test_same_key_different_user_is_rejected(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $service = app(IdempotencyService::class);
        $request = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'same']);
        $request->headers->set('X-Idempotency-Key', 'SHARED-USER-KEY');
        $request->setUserResolver(fn () => $owner);
        $context = $service->resolveContext($request);

        IdempotencyKey::query()->create([
            'user_id' => $context['user_id'],
            'actor_identifier' => $context['actor_identifier'],
            'key' => $context['key'],
            'request_path' => $context['canonical_path'],
            'request_method' => $context['request_method'],
            'canonical_path' => $context['canonical_path'],
            'request_hash' => $context['request_hash'],
            'response_code' => 201,
            'response_body' => ['ok' => true],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = app(IdempotencyMiddleware::class);
        $otherRequest = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'same']);
        $otherRequest->headers->set('X-Idempotency-Key', 'SHARED-USER-KEY');
        $otherRequest->setUserResolver(fn () => $otherUser);

        $response = $middleware->handle($otherRequest, fn () => new JsonResponse(['should_not' => 'run'], 201));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_scope_conflict', $response->getData(true)['error']);
    }

    public function test_same_key_different_endpoint_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(IdempotencyService::class);
        $request = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'same']);
        $request->headers->set('X-Idempotency-Key', 'PATH-KEY');
        $request->setUserResolver(fn () => $user);
        $context = $service->resolveContext($request);

        IdempotencyKey::query()->create([
            'user_id' => $context['user_id'],
            'actor_identifier' => $context['actor_identifier'],
            'key' => $context['key'],
            'request_path' => $context['canonical_path'],
            'request_method' => $context['request_method'],
            'canonical_path' => $context['canonical_path'],
            'request_hash' => $context['request_hash'],
            'response_code' => 201,
            'response_body' => ['ok' => true],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = app(IdempotencyMiddleware::class);
        $otherRequest = Request::create('/api/v1/notifications/events', 'POST', ['notes' => 'same']);
        $otherRequest->headers->set('X-Idempotency-Key', 'PATH-KEY');
        $otherRequest->setUserResolver(fn () => $user);

        $response = $middleware->handle($otherRequest, fn () => new JsonResponse(['should_not' => 'run'], 201));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_scope_conflict', $response->getData(true)['error']);
    }

    public function test_same_key_different_method_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(IdempotencyService::class);
        $request = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'same']);
        $request->headers->set('X-Idempotency-Key', 'METHOD-KEY');
        $request->setUserResolver(fn () => $user);
        $context = $service->resolveContext($request);

        IdempotencyKey::query()->create([
            'user_id' => $context['user_id'],
            'actor_identifier' => $context['actor_identifier'],
            'key' => $context['key'],
            'request_path' => $context['canonical_path'],
            'request_method' => $context['request_method'],
            'canonical_path' => $context['canonical_path'],
            'request_hash' => $context['request_hash'],
            'response_code' => 201,
            'response_body' => ['ok' => true],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = app(IdempotencyMiddleware::class);
        $otherRequest = Request::create('/api/v1/ride-orders', 'PATCH', ['notes' => 'same']);
        $otherRequest->headers->set('X-Idempotency-Key', 'METHOD-KEY');
        $otherRequest->setUserResolver(fn () => $user);

        $response = $middleware->handle($otherRequest, fn () => new JsonResponse(['should_not' => 'run'], 200));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_scope_conflict', $response->getData(true)['error']);
    }

    public function test_same_key_different_body_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(IdempotencyService::class);
        $request = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'first']);
        $request->headers->set('X-Idempotency-Key', 'BODY-KEY');
        $request->setUserResolver(fn () => $user);
        $context = $service->resolveContext($request);

        IdempotencyKey::query()->create([
            'user_id' => $context['user_id'],
            'actor_identifier' => $context['actor_identifier'],
            'key' => $context['key'],
            'request_path' => $context['canonical_path'],
            'request_method' => $context['request_method'],
            'canonical_path' => $context['canonical_path'],
            'request_hash' => $context['request_hash'],
            'response_code' => 201,
            'response_body' => ['ok' => true],
            'expires_at' => now()->addDay(),
        ]);

        $middleware = app(IdempotencyMiddleware::class);
        $otherRequest = Request::create('/api/v1/ride-orders', 'POST', ['notes' => 'second']);
        $otherRequest->headers->set('X-Idempotency-Key', 'BODY-KEY');
        $otherRequest->setUserResolver(fn () => $user);

        $response = $middleware->handle($otherRequest, fn () => new JsonResponse(['should_not' => 'run'], 201));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_payload_mismatch', $response->getData(true)['error']);
    }
}
