<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenNotExpired
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $token = PersonalAccessToken::findToken($bearerToken);

            if ($token && $token->expires_at && $token->expires_at->isPast()) {
                $token->delete();

                return response()->json([
                    'error' => 'token_expired',
                    'message' => 'Your session has expired. Please login again.',
                ], 401);
            }
        }

        return $next($request);
    }
}
