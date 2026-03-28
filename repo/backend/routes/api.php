<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RideOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware(['token.not_expired', 'auth:sanctum'])->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:driver,admin'])->get('/driver/queue', function () {
        return response()->json(['message' => 'Driver queue access granted']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:admin'])->get('/admin/panel', function () {
        return response()->json(['message' => 'Admin panel access granted']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:rider'])->group(function (): void {
        Route::post('/ride-orders', [RideOrderController::class, 'store']);
        Route::get('/ride-orders', [RideOrderController::class, 'index']);
        Route::patch('/ride-orders/{rideOrder}/transition', [RideOrderController::class, 'transition']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum'])->get('/ride-orders/{rideOrder}', [RideOrderController::class, 'show']);
});
