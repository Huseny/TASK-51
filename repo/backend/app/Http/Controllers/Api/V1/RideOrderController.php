<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\RideOrderRequest;
use App\Http\Requests\Rides\RideOrderTransitionRequest;
use App\Models\RideOrder;
use App\Models\RideOrderAuditLog;
use App\Services\DriverScheduleService;
use App\Services\NotificationService;
use App\Services\RideOrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RideOrderController extends Controller
{
    public function __construct(
        private readonly RideOrderStateMachine $stateMachine,
        private readonly DriverScheduleService $driverScheduleService,
        private readonly NotificationService $notificationService,
    )
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
        $rideOrder->load(['driver:id,username']);

        return response()->json([
            'order' => $rideOrder,
            'is_cancellable' => in_array($rideOrder->status, ['matching', 'accepted'], true),
            'time_until_auto_cancel' => $this->timeUntilAutoCancel($rideOrder),
        ]);
    }

    public function transition(RideOrderTransitionRequest $request, RideOrder $rideOrder): JsonResponse
    {
        $action = $request->validated('action');
        $actor = $request->user();

        if ($action === 'cancel') {
            $this->authorize('cancel', $rideOrder);
        } elseif ($action === 'accept') {
            if ($rideOrder->status === 'matching') {
                $this->authorize('accept', $rideOrder);
            } elseif (! in_array($actor->role, ['driver', 'admin'], true)) {
                $this->authorize('accept', $rideOrder);
            }

            if ($rideOrder->status === 'matching' && $this->driverScheduleService->hasOverlap($actor, $rideOrder)) {
                return response()->json([
                    'error' => 'schedule_conflict',
                    'message' => 'You already have a ride during this time window',
                ], 422);
            }
        } else {
            $this->authorize('driverAction', $rideOrder);
        }

        $order = $this->stateMachine->transition(
            $rideOrder,
            $action,
            $actor,
            [
                'reason' => $request->validated('reason'),
                'exception_reason' => $action === 'flag_exception' ? $request->validated('reason') : null,
            ],
        );

        if ($action === 'flag_exception') {
            $this->stateMachine->transition($order, 'reassign', null, [
                'reason' => 'exception_reassignment',
                'exception_reason' => $request->validated('reason'),
            ]);
        }

        $this->logDriverAction($action, $actor->id, $rideOrder->id, $request->validated('reason'));

        if ($action === 'complete' && $order->rider) {
            $this->notificationService->send(
                $order->rider,
                'ride_update',
                'Ride completed',
                sprintf('Your ride #%d has been completed.', $order->id),
                [
                    'ride_id' => $order->id,
                    'url' => '/rider/trips/'.$order->id,
                ],
                'ride_'.$order->id.'_updates'
            );
        }

        return response()->json([
            'order' => $order->load(['auditLogs' => fn ($query) => $query->orderBy('created_at')]),
            'is_cancellable' => in_array($order->status, ['matching', 'accepted'], true),
            'time_until_auto_cancel' => $this->timeUntilAutoCancel($order),
        ]);
    }

    private function logDriverAction(string $action, int $actorId, int $orderId, ?string $reason): void
    {
        if (! in_array($action, ['accept', 'start', 'complete', 'flag_exception'], true)) {
            return;
        }

        if ($action === 'flag_exception') {
            Log::channel('app')->info(
                sprintf('Driver #%d flagged exception on ride #%d: %s', $actorId, $orderId, (string) $reason),
                ['driver_id' => $actorId, 'ride_order_id' => $orderId]
            );

            return;
        }

        $messages = [
            'accept' => 'accepted',
            'start' => 'started',
            'complete' => 'completed',
        ];

        Log::channel('app')->info(
            sprintf('Driver #%d %s ride #%d', $actorId, $messages[$action], $orderId),
            ['driver_id' => $actorId, 'ride_order_id' => $orderId]
        );
    }

    private function timeUntilAutoCancel(RideOrder $rideOrder): ?int
    {
        if ($rideOrder->status !== 'matching') {
            return null;
        }

        return max(0, now()->diffInSeconds($rideOrder->created_at->copy()->addMinutes(10), false));
    }
}
