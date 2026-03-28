<?php

namespace App\Services;

use App\Exceptions\InvalidTransitionException;
use App\Models\RideOrder;
use App\Models\RideOrderAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RideOrderStateMachine
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(RideOrder $order, string $action, ?User $actor = null, array $metadata = []): RideOrder
    {
        return DB::transaction(function () use ($order, $action, $actor, $metadata): RideOrder {
            /** @var RideOrder $lockedOrder */
            $lockedOrder = RideOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $targetStatus = $this->targetStatusForAction($action);

            if ($lockedOrder->status === $targetStatus) {
                if ($this->isIdempotentAction($action, $lockedOrder, $actor)) {
                    return $lockedOrder->fresh(['auditLogs']);
                }

                throw new InvalidTransitionException(sprintf(
                    'Cannot %s ride order from %s to %s.',
                    $action,
                    $lockedOrder->status,
                    $targetStatus,
                ));
            }

            $transition = $this->resolveTransition($lockedOrder, $action, $actor, $metadata);

            $fromStatus = $lockedOrder->status;
            $lockedOrder->fill($transition['updates']);
            $lockedOrder->status = $transition['to_status'];
            $lockedOrder->save();

            RideOrderAuditLog::query()->create([
                'ride_order_id' => $lockedOrder->id,
                'from_status' => $fromStatus,
                'to_status' => $transition['to_status'],
                'triggered_by' => $actor ? (string) $actor->id : 'system',
                'trigger_reason' => $transition['reason'],
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            Log::channel('app')->info('Ride order transition completed', [
                'ride_order_id' => $lockedOrder->id,
                'from_status' => $fromStatus,
                'to_status' => $transition['to_status'],
                'action' => $action,
                'actor_id' => $actor?->id,
            ]);

            return $lockedOrder->fresh(['auditLogs']);
        });
    }

    private function targetStatusForAction(string $action): string
    {
        return match ($action) {
            'submit' => 'matching',
            'accept' => 'accepted',
            'start' => 'in_progress',
            'complete' => 'completed',
            'cancel' => 'canceled',
            'flag_exception' => 'exception',
            'reassign' => 'matching',
            default => throw new InvalidTransitionException('Unknown transition action: '.$action),
        };
    }

    private function isIdempotentAction(string $action, RideOrder $order, ?User $actor): bool
    {
        return match ($action) {
            'submit', 'cancel', 'reassign' => true,
            'accept', 'start', 'complete', 'flag_exception' => $actor !== null && $order->driver_id === $actor->id,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveTransition(RideOrder $order, string $action, ?User $actor, array $metadata): array
    {
        return match ($action) {
            'submit' => $this->resolveSubmit($order),
            'accept' => $this->resolveAccept($order, $actor),
            'start' => $this->resolveStart($order, $actor),
            'complete' => $this->resolveComplete($order, $actor),
            'cancel' => $this->resolveCancel($order, $actor, $metadata),
            'flag_exception' => $this->resolveFlagException($order, $actor, $metadata),
            'reassign' => $this->resolveReassign($order, $metadata),
            default => throw new InvalidTransitionException('Unknown transition action: '.$action),
        };
    }

    /**
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveSubmit(RideOrder $order): array
    {
        $this->assertCurrentStatus($order, ['created'], 'submit', 'matching');

        return [
            'to_status' => 'matching',
            'updates' => [],
            'reason' => 'order_submitted',
        ];
    }

    /**
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveAccept(RideOrder $order, ?User $actor): array
    {
        $this->assertCurrentStatus($order, ['matching'], 'accept', 'accepted');
        $this->assertDriverActor($actor, 'accept');

        return [
            'to_status' => 'accepted',
            'updates' => [
                'driver_id' => $actor->id,
                'accepted_at' => now(),
            ],
            'reason' => 'driver_accepted',
        ];
    }

    /**
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveStart(RideOrder $order, ?User $actor): array
    {
        $this->assertCurrentStatus($order, ['accepted'], 'start', 'in_progress');
        $this->assertDriverActor($actor, 'start');

        return [
            'to_status' => 'in_progress',
            'updates' => [
                'started_at' => now(),
            ],
            'reason' => 'driver_started_trip',
        ];
    }

    /**
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveComplete(RideOrder $order, ?User $actor): array
    {
        $this->assertCurrentStatus($order, ['in_progress'], 'complete', 'completed');
        $this->assertDriverActor($actor, 'complete');

        return [
            'to_status' => 'completed',
            'updates' => [
                'completed_at' => now(),
            ],
            'reason' => 'driver_completed_trip',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveCancel(RideOrder $order, ?User $actor, array $metadata): array
    {
        $this->assertCurrentStatus($order, ['matching', 'accepted'], 'cancel', 'canceled');

        $reason = isset($metadata['reason']) && is_string($metadata['reason'])
            ? $metadata['reason']
            : ($actor ? 'rider_canceled' : 'system_canceled');

        return [
            'to_status' => 'canceled',
            'updates' => [
                'canceled_at' => now(),
                'cancellation_reason' => $reason,
            ],
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveFlagException(RideOrder $order, ?User $actor, array $metadata): array
    {
        $this->assertCurrentStatus($order, ['in_progress'], 'flag_exception', 'exception');
        $this->assertDriverActor($actor, 'flag_exception');

        $reason = isset($metadata['reason']) && is_string($metadata['reason'])
            ? $metadata['reason']
            : 'driver_flagged_exception';

        return [
            'to_status' => 'exception',
            'updates' => [],
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{to_status: string, updates: array<string, mixed>, reason: string}
     */
    private function resolveReassign(RideOrder $order, array $metadata): array
    {
        $this->assertCurrentStatus($order, ['accepted', 'exception'], 'reassign', 'matching');

        if ($order->status === 'accepted' && $order->started_at !== null) {
            throw new InvalidTransitionException('Cannot reassign an accepted ride that already started.');
        }

        $reason = isset($metadata['reason']) && is_string($metadata['reason'])
            ? $metadata['reason']
            : ($order->status === 'accepted' ? 'no_show_auto_revert' : 'exception_reassignment');

        return [
            'to_status' => 'matching',
            'updates' => [
                'driver_id' => null,
                'accepted_at' => null,
            ],
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     */
    private function assertCurrentStatus(RideOrder $order, array $allowedStatuses, string $action, string $targetStatus): void
    {
        if (! in_array($order->status, $allowedStatuses, true)) {
            throw new InvalidTransitionException(sprintf(
                'Cannot %s ride order from %s to %s.',
                $action,
                $order->status,
                $targetStatus,
            ));
        }
    }

    private function assertDriverActor(?User $actor, string $action): void
    {
        if (! $actor || ! in_array($actor->role, ['driver', 'admin'], true)) {
            throw new InvalidTransitionException(sprintf('Action %s requires a driver actor.', $action));
        }
    }
}
