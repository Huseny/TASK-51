<?php

namespace Tests\Feature\Rides;

use App\Exceptions\InvalidTransitionException;
use App\Models\RideOrder;
use App\Models\User;
use App\Services\RideOrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideOrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transitions_for_all_state_machine_arrows(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $created = RideOrder::factory()->create(['status' => 'created', 'rider_id' => $rider->id]);
        $matching = $stateMachine->transition($created, 'submit');
        $this->assertSame('matching', $matching->status);

        $matchingForAccept = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);
        $accepted = $stateMachine->transition($matchingForAccept, 'accept', $driver);
        $this->assertSame('accepted', $accepted->status);

        $matchingCancelByRider = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);
        $canceledByRider = $stateMachine->transition($matchingCancelByRider, 'cancel', $rider, ['reason' => 'rider_canceled']);
        $this->assertSame('canceled', $canceledByRider->status);

        $matchingCancelBySystem = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);
        $canceledBySystem = $stateMachine->transition($matchingCancelBySystem, 'cancel', null, ['reason' => 'auto_cancel_timeout']);
        $this->assertSame('canceled', $canceledBySystem->status);

        $acceptedForStart = RideOrder::factory()->create([
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinute(),
        ]);
        $inProgress = $stateMachine->transition($acceptedForStart, 'start', $driver);
        $this->assertSame('in_progress', $inProgress->status);

        $acceptedForReassign = RideOrder::factory()->create([
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinutes(6),
        ]);
        $reassigned = $stateMachine->transition($acceptedForReassign, 'reassign', null, ['reason' => 'no_show_auto_revert']);
        $this->assertSame('matching', $reassigned->status);

        $acceptedForCancel = RideOrder::factory()->create([
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinute(),
        ]);
        $canceledAccepted = $stateMachine->transition($acceptedForCancel, 'cancel', $rider, ['reason' => 'rider_canceled']);
        $this->assertSame('canceled', $canceledAccepted->status);

        $inProgressForComplete = RideOrder::factory()->create([
            'status' => 'in_progress',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'started_at' => now()->subMinutes(5),
        ]);
        $completed = $stateMachine->transition($inProgressForComplete, 'complete', $driver);
        $this->assertSame('completed', $completed->status);

        $inProgressForException = RideOrder::factory()->create([
            'status' => 'in_progress',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'started_at' => now()->subMinutes(2),
        ]);
        $exception = $stateMachine->transition($inProgressForException, 'flag_exception', $driver, ['reason' => 'flat_tire']);
        $this->assertSame('exception', $exception->status);

        $exceptionForReassign = RideOrder::factory()->create([
            'status' => 'exception',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinutes(10),
        ]);
        $fromException = $stateMachine->transition($exceptionForReassign, 'reassign', null, ['reason' => 'exception_reassignment']);
        $this->assertSame('matching', $fromException->status);
    }

    public function test_invalid_transition_throws_clear_error(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $order = RideOrder::factory()->create(['status' => 'completed']);

        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Cannot reassign ride order from completed to matching.');

        $stateMachine->transition($order, 'reassign');
    }

    public function test_idempotent_cancel_on_already_canceled_order_returns_without_new_audit_log(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'canceled',
            'canceled_at' => now()->subMinute(),
        ]);

        $beforeCount = $order->auditLogs()->count();

        $transitioned = $stateMachine->transition($order, 'cancel', $rider, ['reason' => 'duplicate_cancel_request']);

        $this->assertSame('canceled', $transitioned->status);
        $this->assertSame($beforeCount, $order->fresh()->auditLogs()->count());
    }

    public function test_two_drivers_trying_to_accept_same_ride_only_one_succeeds(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $driverA = User::factory()->create(['role' => 'driver']);
        $driverB = User::factory()->create(['role' => 'driver']);
        $order = RideOrder::factory()->create(['status' => 'matching']);

        $firstRead = RideOrder::query()->findOrFail($order->id);
        $secondRead = RideOrder::query()->findOrFail($order->id);

        $accepted = $stateMachine->transition($firstRead, 'accept', $driverA);
        $this->assertSame('accepted', $accepted->status);
        $this->assertSame($driverA->id, $accepted->driver_id);

        $this->expectException(InvalidTransitionException::class);

        $stateMachine->transition($secondRead, 'accept', $driverB);
    }

    public function test_reassign_audit_log_captures_previous_driver_new_driver_and_reason(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'status' => 'accepted',
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'accepted_at' => now()->subMinutes(6),
        ]);

        $reassigned = $stateMachine->transition($order, 'reassign', null, ['reason' => 'no_show_auto_revert']);
        $auditLog = $reassigned->auditLogs()->latest('created_at')->firstOrFail();

        $this->assertSame('matching', $reassigned->status);
        $this->assertSame([
            'reason' => 'no_show_auto_revert',
            'previous_driver_id' => $driver->id,
            'new_driver_id' => null,
            'driver_changed' => true,
            'driver_reassigned' => true,
            'reassignment_reason' => 'no_show_auto_revert',
        ], array_intersect_key($auditLog->metadata ?? [], array_flip([
            'reason',
            'previous_driver_id',
            'new_driver_id',
            'driver_changed',
            'driver_reassigned',
            'reassignment_reason',
        ])));
    }
}
