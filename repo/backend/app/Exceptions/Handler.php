<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler
{
    public function render(Request $request, Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Request validation failed',
                'details' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof InvalidTransitionException) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $exception->getMessage(),
                'details' => (object) [],
            ], 422);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Authentication is required to access this resource',
                'details' => (object) [],
            ], 401);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not authorized to perform this action',
                'details' => (object) [],
            ], 403);
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not authorized to perform this action',
                'details' => (object) [],
            ], 403);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'The requested resource was not found',
                'details' => (object) [],
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'The requested resource was not found',
                'details' => (object) [],
            ], 404);
        }

        if ($exception instanceof TooManyRequestsHttpException || $exception instanceof ThrottleRequestsException) {
            return response()->json([
                'error' => 'too_many_requests',
                'message' => 'Too many requests, please try again later',
                'details' => (object) [],
            ], 429);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if ($status === 401) {
                return response()->json([
                    'error' => 'unauthenticated',
                    'message' => 'Authentication is required to access this resource',
                    'details' => (object) [],
                ], 401);
            }

            if ($status === 403) {
                return response()->json([
                    'error' => 'forbidden',
                    'message' => 'You are not authorized to perform this action',
                    'details' => (object) [],
                ], 403);
            }

            if ($status === 404) {
                return response()->json([
                    'error' => 'not_found',
                    'message' => 'The requested resource was not found',
                    'details' => (object) [],
                ], 404);
            }
        }

        Log::error('Unhandled API exception', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'path' => $request->path(),
        ]);

        return response()->json([
            'error' => 'internal_server_error',
            'message' => 'An unexpected error occurred',
            'details' => (object) [],
        ], 500);
    }
}
