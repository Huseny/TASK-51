<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateExportAccess
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->hasValidSignature()) {
            return $next($request);
        }

        return response()->json([
            'error' => 'link_expired',
            'message' => 'This export link has expired. Please generate a new export.',
        ], 403);
    }
}
