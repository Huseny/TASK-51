<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DriverRideController;
use App\Http\Controllers\Api\V1\FleetRideController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\GroupChatController;
use App\Http\Controllers\Api\V1\InteractionController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationScenarioController;
use App\Http\Controllers\Api\V1\NotificationSubscriptionController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\ReadinessController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RideOrderController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/readiness', ReadinessController::class);

Route::prefix('v1')->middleware('idempotency')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware(['token.not_expired', 'auth:sanctum'])->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:admin'])->get('/admin/panel', function () {
        return response()->json(['message' => 'Admin panel access granted']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:driver,admin'])->group(function (): void {
        Route::get('/driver/queue', function () {
            return response()->json(['message' => 'Driver queue access granted']);
        });
        Route::get('/driver/available-rides', [DriverRideController::class, 'availableRides']);
        Route::get('/driver/my-rides', [DriverRideController::class, 'myRides']);
        Route::get('/driver/my-rides/{rideOrder}', [DriverRideController::class, 'showMyRide']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:fleet_manager,admin'])->prefix('fleet')->group(function (): void {
        Route::get('/rides/queue', [FleetRideController::class, 'queue']);
        Route::get('/rides/active', [FleetRideController::class, 'active']);
        Route::get('/rides/{rideOrder}', [FleetRideController::class, 'show']);
        Route::get('/drivers', [FleetRideController::class, 'drivers']);
        Route::patch('/rides/{rideOrder}/assign', [FleetRideController::class, 'assign']);
        Route::patch('/rides/{rideOrder}/reassign', [FleetRideController::class, 'reassign']);
        Route::patch('/rides/{rideOrder}/cancel', [FleetRideController::class, 'cancel']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:rider'])->group(function (): void {
        Route::post('/ride-orders', [RideOrderController::class, 'store']);
        Route::get('/ride-orders', [RideOrderController::class, 'index']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum'])->patch('/ride-orders/{rideOrder}/transition', [RideOrderController::class, 'transition']);
    Route::middleware(['token.not_expired', 'auth:sanctum'])->get('/ride-orders/{rideOrder}', [RideOrderController::class, 'show']);

    Route::middleware(['token.not_expired', 'auth:sanctum'])->group(function (): void {
        Route::get('/ride-orders/{rideOrder}/chat', [GroupChatController::class, 'showByRide']);
        Route::post('/group-chats/{chat}/messages', [GroupChatController::class, 'sendMessage']);
        Route::get('/group-chats/{chat}/messages', [GroupChatController::class, 'getMessages']);
        Route::post('/group-chats/{chat}/read', [GroupChatController::class, 'markRead']);
        Route::patch('/group-chats/{chat}/dnd', [GroupChatController::class, 'updateDnd']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:driver,fleet_manager,admin'])->group(function (): void {
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

        Route::post('/vehicles/{vehicle}/media', [VehicleController::class, 'uploadMedia']);
        Route::patch('/vehicles/{vehicle}/media/reorder', [VehicleController::class, 'reorderMedia']);
        Route::patch('/vehicles/{vehicle}/media/{mediaId}/cover', [VehicleController::class, 'setCover']);
        Route::delete('/vehicles/{vehicle}/media/{mediaId}', [VehicleController::class, 'removeMedia']);

        Route::get('/media/{media}/url', [MediaController::class, 'url']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum'])->group(function (): void {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::post('/interactions', [InteractionController::class, 'store']);
        Route::get('/recommendations', [RecommendationController::class, 'index']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/events', [NotificationScenarioController::class, 'store'])->middleware('throttle:20,1');

        Route::post('/follows', [FollowController::class, 'store']);

        Route::get('/notification-subscriptions', [NotificationSubscriptionController::class, 'index']);
        Route::post('/notification-subscriptions', [NotificationSubscriptionController::class, 'store']);
        Route::delete('/notification-subscriptions/{notificationSubscription}', [NotificationSubscriptionController::class, 'destroy']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:fleet_manager,admin'])->group(function (): void {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::patch('/products/{product}/publish', [ProductController::class, 'publish']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:rider,driver'])->group(function (): void {
        Route::post('/products/{product}/purchase', [ProductController::class, 'purchase']);
    });

    Route::middleware(['token.not_expired', 'auth:sanctum', 'role:admin,fleet_manager'])->prefix('reports')->group(function (): void {
        Route::get('/trends', [ReportController::class, 'trends']);
        Route::get('/distribution', [ReportController::class, 'distribution']);
        Route::get('/regions', [ReportController::class, 'regions']);
        Route::get('/export-directories', [ReportController::class, 'exportDirectories']);
        Route::post('/export', [ReportController::class, 'export']);

        Route::get('/templates', [ReportController::class, 'templates']);
        Route::post('/templates', [ReportController::class, 'storeTemplate']);
        Route::patch('/templates/{template}', [ReportController::class, 'updateTemplate']);
        Route::delete('/templates/{template}', [ReportController::class, 'destroyTemplate']);
    });

    Route::get('/media/{media}/download', [MediaController::class, 'download'])
        ->name('media.download')
        ->middleware(['media.access', 'token.not_expired', 'auth:sanctum']);

    Route::get('/reports/exports/{reportExport}', [ReportController::class, 'download'])
        ->name('reports.exports.download')
        ->middleware(['export.access', 'token.not_expired', 'auth:sanctum', 'role:admin,fleet_manager']);
});
