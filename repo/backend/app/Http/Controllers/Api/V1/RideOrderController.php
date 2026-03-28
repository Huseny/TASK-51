<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\RideOrderRequest;
use App\Http\Requests\Rides\RideOrderTransitionRequest;
use App\Models\RideOrder;
use App\Models\RideOrderAuditLog;
use App\Services\RideOrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RideOrderController extends Controller
{
    public function __construct(private readonly RideOrderStateMachine $stateMachine)
    {
    }

    public function store(RideOrderRequest $request): JsonResponse
    {
        $user = $request->user();

        $order = RideOrder::query()->create([
            'rider_id' => $user->id,
            ...$request->validated(),
            'status' => 'created',
        ]);

        RideOrderAuditLog::query()->create([
            'ride_order_id' => $order->id,
            'from_status' => 'created',
            'to_status' => 'created',
            'triggered_by' => (string) $user->id,
            'trigger_reason' => 'ride_order_created',
            'metadata' => null,
            'created_at' => now(),
        ]);

        $order = $this->stateMachine->transition($order, 'submit');

        Log::channel('auth')->info(sprintf('Ride order #%d created by rider #%d', $order->id, $user->id), [
            'ride_order_id' => $order->id,
            'rider_id' => $user->id,
        ]);

        return response()->json([
            'order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')]),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 50));

        $query = RideOrder::query()
            ->where('rider_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('view', $rideOrder);

        $rideOrder->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')]);

        return response()->json([
            'order' => $rideOrder,
            'is_cancellable' => in_array($rideOrder->status, ['matching', 'accepted'], true),
            'time_until_auto_cancel' => $this->timeUntilAutoCancel($rideOrder),
        ]);
    }

    public function transition(RideOrderTransitionRequest $request, RideOrder $rideOrder): JsonResponse
    {
        $this->authorize('cancel', $rideOrder);

        $order = $this->stateMachine->transition(
            $rideOrder,
            $request->validated('action'),
            $request->user(),
            ['reason' => $request->validated('reason')],
        );

        return response()->json([
            'order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')]),
            'is_cancellable' => in_array($order->status, ['matching', 'accepted'], true),
            'time_until_auto_cancel' => $this->timeUntilAutoCancel($order),
        ]);
    }

    private function timeUntilAutoCancel(RideOrder $rideOrder): ?int
    {
        if ($rideOrder->status !== 'matching') {
            return null;
        }

        return max(0, now()->diffInSeconds($rideOrder->created_at->copy()->addMinutes(10), false));
    }
}
