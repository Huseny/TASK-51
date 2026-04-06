<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyService
{
    /**
     * @return array{key:string, user_id:int|null, actor_identifier:string, request_method:string, canonical_path:string, request_hash:string}
     */
    public function resolveContext(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'key' => (string) $request->header('X-Idempotency-Key', ''),
            'user_id' => $userId,
            'actor_identifier' => $userId !== null
                ? 'user:'.$userId
                : 'guest:'.hash('sha256', sprintf('%s|%s', (string) $request->ip(), (string) $request->userAgent())),
            'request_method' => strtoupper($request->method()),
            'canonical_path' => $this->canonicalPath($request),
            'request_hash' => hash('sha256', json_encode($this->normalizedPayload($request), JSON_THROW_ON_ERROR)),
        ];
    }

    public function shouldHandle(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        return (string) $request->header('X-Idempotency-Key', '') !== '';
    }

    /**
     * @return array{type:'created'|'replay'|'conflict', record?:IdempotencyKey, response?:JsonResponse, context:array{key:string, user_id:int|null, actor_identifier:string, request_method:string, canonical_path:string, request_hash:string}}
     */
    public function begin(Request $request): array
    {
        $context = $this->resolveContext($request);

        return DB::transaction(function () use ($context): array {
            IdempotencyKey::query()
                ->where('key', $context['key'])
                ->where('expires_at', '<=', now())
                ->delete();

            $sameScope = $this->sameScopeQuery($context)->lockForUpdate()->first();
            if ($sameScope) {
                return $this->resolveExistingRecord($sameScope, $context);
            }

            $crossContext = IdempotencyKey::query()
                ->where('key', $context['key'])
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($crossContext) {
                return [
                    'type' => 'conflict',
                    'response' => $this->conflictResponse(
                        'idempotency_scope_conflict',
                        'This idempotency key is already bound to a different request context.'
                    ),
                    'context' => $context,
                ];
            }

            try {
                $record = IdempotencyKey::query()->create([
                    'user_id' => $context['user_id'],
                    'actor_identifier' => $context['actor_identifier'],
                    'key' => $context['key'],
                    'request_path' => $context['canonical_path'],
                    'request_method' => $context['request_method'],
                    'canonical_path' => $context['canonical_path'],
                    'request_hash' => $context['request_hash'],
                    'response_code' => null,
                    'response_body' => null,
                    'expires_at' => now()->addDay(),
                ]);

                return [
                    'type' => 'created',
                    'record' => $record,
                    'context' => $context,
                ];
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $record = $this->sameScopeQuery($context)->lockForUpdate()->first();
                if (! $record) {
                    return [
                        'type' => 'conflict',
                        'response' => $this->conflictResponse(
                            'idempotency_conflict',
                            'A request with this idempotency key is already being processed.'
                        ),
                        'context' => $context,
                    ];
                }

                return $this->resolveExistingRecord($record, $context);
            }
        });
    }

    public function storeResponse(IdempotencyKey $record, Response $response): void
    {
        $record->forceFill([
            'response_code' => $response->getStatusCode(),
            'response_body' => $this->extractResponseBody($response),
        ])->save();
    }

    /**
     * @param  array{key:string, user_id:int|null, actor_identifier:string, request_method:string, canonical_path:string, request_hash:string}  $context
     * @return array{type:'replay'|'conflict', response:JsonResponse, context:array{key:string, user_id:int|null, actor_identifier:string, request_method:string, canonical_path:string, request_hash:string}}
     */
    private function resolveExistingRecord(IdempotencyKey $record, array $context): array
    {
        if ($record->request_hash !== $context['request_hash']) {
            return [
                'type' => 'conflict',
                'response' => $this->conflictResponse(
                    'idempotency_payload_mismatch',
                    'This idempotency key has already been used with a different request payload.'
                ),
                'context' => $context,
            ];
        }

        if ($record->response_code === null) {
            return [
                'type' => 'conflict',
                'response' => $this->conflictResponse(
                    'idempotency_conflict',
                    'A request with this idempotency key is already being processed.'
                ),
                'context' => $context,
            ];
        }

        return [
            'type' => 'replay',
            'response' => response()->json($record->response_body ?? (object) [], $record->response_code),
            'context' => $context,
        ];
    }

    /**
     * @param  array{key:string, user_id:int|null, actor_identifier:string, request_method:string, canonical_path:string, request_hash:string}  $context
     */
    private function sameScopeQuery(array $context)
    {
        return IdempotencyKey::query()
            ->where('actor_identifier', $context['actor_identifier'])
            ->where('request_method', $context['request_method'])
            ->where('canonical_path', $context['canonical_path'])
            ->where('key', $context['key'])
            ->where('expires_at', '>', now());
    }

    private function canonicalPath(Request $request): string
    {
        $uri = $request->route()?->uri();
        $path = is_string($uri) && $uri !== '' ? $uri : $request->path();

        return ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request): array
    {
        return [
            'body' => $this->normalizeValue($request->all()),
            'query' => $this->normalizeValue($request->query()),
        ];
    }

    /**
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeValue($item);
        }

        return $value;
    }

    private function conflictResponse(string $error, string $message): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'message' => $message,
            'details' => (object) [],
        ], 409);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractResponseBody(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            $decoded = $response->getData(true);

            return is_array($decoded) ? $decoded : ['value' => $decoded];
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => $content];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
