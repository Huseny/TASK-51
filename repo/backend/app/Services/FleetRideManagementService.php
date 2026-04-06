<?php

namespace App\Services;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FleetRideManagementService
{
    public function __construct(
        private readonly RideOrderStateMachine $stateMachine,
        private readonly DriverScheduleService $driverScheduleService,
    ) {
    }

    public function queue(Request $request)
    {
        return $this->baseQuery($request)
            ->where('status', 'matching')
            ->orderBy('time_window_start')
            ->paginate($this->perPage($request));
    }

    public function active(Request $request)
    {
        return $this->baseQuery($request)
            ->whereIn('status', ['accepted', 'in_progress', 'exception'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'accepted' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate($this->perPage($request));
    }

    public function assignDriver(RideOrder $order, int $driverId, ?string $reason = null): RideOrder
    {
        $driver = $this->resolveDriver($driverId);

        if ($order->status !== 'matching') {
            throw new \InvalidArgumentException('Only matching rides can be assigned directly.');
        }

        if ($this->driverScheduleService->hasOverlap($driver, $order)) {
            throw new \RuntimeException('Selected driver already has an overlapping ride.');
        }

        return $this->stateMachine->transition($order, 'accept', $driver, [
            'reason' => $reason ?? 'fleet_manual_assignment',
            'assigned_by_role' => 'fleet_manager',
        ]);
    }

    public function reassignRide(RideOrder $order, ?int $driverId = null, ?string $reason = null): RideOrder
    {
        $manualReason = $reason ?? 'manual_reassignment';
        $reassigned = $order;

        if (in_array($order->status, ['accepted', 'exception'], true)) {
            $reassigned = $this->stateMachine->transition($order, 'reassign', null, [
                'reason' => $manualReason,
                'reassignment_mode' => 'fleet_manual',
            ]);
        }

        if ($driverId === null) {
            return $reassigned;
        }

        return $this->assignDriver($reassigned->fresh(), $driverId, $manualReason);
    }

    public function cancelRide(RideOrder $order, ?string $reason = null): RideOrder
    {
        return $this->stateMachine->transition($order, 'cancel', null, [
            'reason' => $reason ?? 'fleet_canceled',
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    public function drivers(): Collection
    {
        return User::query()
            ->where('role', 'driver')
            ->orderBy('username')
            ->get(['id', 'username']);
    }

    private function resolveDriver(int $driverId): User
    {
        /** @var User $driver */
        $driver = User::query()
            ->where('role', 'driver')
            ->findOrFail($driverId);

        return $driver;
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 20), 50));
    }

    private function baseQuery(Request $request): Builder
    {
        $query = RideOrder::query()
            ->with([
                'auditLogs' => fn ($audit) => $audit->orderBy('created_at'),
                'rider:id,username',
                'driver:id,username',
            ]);

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->query('status')))
                ->map(fn (string $status) => trim($status))
                ->filter()
                ->values();

            if ($statuses->isNotEmpty()) {
                $query->whereIn('status', $statuses->all());
            }
        }

        return $query;
    }
}
