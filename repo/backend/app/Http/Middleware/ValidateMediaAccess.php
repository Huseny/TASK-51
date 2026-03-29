<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMediaAccess
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->hasValidSignature()) {
            return $next($request);
        }

        $referer = (string) $request->headers->get('referer', '');
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($referer, $appUrl)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'link_expired',
            'message' => 'This download link has expired. Please request a new one.',
        ], 403);
    }
}
