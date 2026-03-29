<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = (string) $request->header('X-Idempotency-Key', '');
        if ($key === '') {
            return $next($request);
        }

        $record = IdempotencyKey::query()->where('key', $key)->first();

        if ($record && $record->expires_at->isFuture()) {
            if ($record->response_code !== null) {
                return response()->json($record->response_body ?? (object) [], $record->response_code);
            }

            return response()->json([
                'error' => 'idempotency_conflict',
                'message' => 'A request with this idempotency key is already being processed',
                'details' => (object) [],
            ], 409);
        }

        if ($record && $record->expires_at->isPast()) {
            $record->delete();
            $record = null;
        }

        if (! $record) {
            $record = IdempotencyKey::query()->create([
                'key' => $key,
                'request_path' => $request->path(),
                'request_method' => $request->method(),
                'response_code' => null,
                'response_body' => null,
                'expires_at' => now()->addDay(),
            ]);
        }

        $response = $next($request);

        $body = $this->extractResponseBody($response);

        $record->response_code = $response->getStatusCode();
        $record->response_body = $body;
        $record->save();

        return $response;
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
}
