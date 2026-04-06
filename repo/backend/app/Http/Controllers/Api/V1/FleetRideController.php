<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\FleetRideAssignRequest;
use App\Http\Requests\Rides\FleetRideCancelRequest;
use App\Http\Requests\Rides\FleetRideReassignRequest;
use App\Models\RideOrder;
use App\Services\FleetRideManagementService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class FleetRideController extends Controller
{
    public function __construct(private readonly FleetRideManagementService $fleetRideManagementService)
    {
    }

    public function queue(\Illuminate\Http\Request $request): JsonResponse
    {
        return response()->json($this->fleetRideManagementService->queue($request));
    }

    public function active(\Illuminate\Http\Request $request): JsonResponse
    {
        return response()->json($this->fleetRideManagementService->active($request));
    }

    public function show(RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('fleetView', $rideOrder);

        $rideOrder->load([
            'auditLogs' => fn ($query) => $query->orderBy('created_at'),
            'rider:id,username',
            'driver:id,username',
        ]);

        return response()->json(['order' => $rideOrder]);
    }

    public function drivers(): JsonResponse
    {
        return response()->json(['data' => $this->fleetRideManagementService->drivers()]);
    }

    public function assign(FleetRideAssignRequest $request, RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('fleetManage', $rideOrder);

        try {
            $order = $this->fleetRideManagementService->assignDriver(
                $rideOrder,
                (int) $request->validated('driver_id'),
                $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'error' => 'schedule_conflict',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')])]);
    }

    public function reassign(FleetRideReassignRequest $request, RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('fleetManage', $rideOrder);

        try {
            $order = $this->fleetRideManagementService->reassignRide(
                $rideOrder,
                $request->filled('driver_id') ? (int) $request->validated('driver_id') : null,
                $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'error' => 'schedule_conflict',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')])]);
    }

    public function cancel(FleetRideCancelRequest $request, RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('fleetManage', $rideOrder);

        $order = $this->fleetRideManagementService->cancelRide(
            $rideOrder,
            $request->validated('reason'),
        );

        return response()->json(['order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')])]);
    }
}
