<?php

namespace Tests\Feature\Chat;

use App\Models\GroupChat;
use App\Models\RideOrder;
use App\Models\User;
use App\Services\RideOrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_created_notice_on_accept(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider', 'username' => 'rider01']);
        $driver = User::factory()->create(['role' => 'driver', 'username' => 'driver01']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $stateMachine->transition($ride, 'accept', $driver);
        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();

        $this->assertDatabaseHas('group_messages', [
            'group_chat_id' => $chat->id,
            'type' => 'system_notice',
            'content' => 'Group ride created - rider01 and driver01 are now connected.',
        ]);
    }

    public function test_group_disbanded_notice_on_complete(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $accepted = $stateMachine->transition($ride, 'accept', $driver);
        $inProgress = $stateMachine->transition($accepted, 'start', $driver);
        $stateMachine->transition($inProgress, 'complete', $driver);

        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();

        $this->assertDatabaseHas('group_messages', [
            'group_chat_id' => $chat->id,
            'content' => 'Group has been disbanded - ride completed.',
        ]);
    }

    public function test_user_left_notice_when_driver_removed(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver', 'username' => 'driverOld']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $accepted = $stateMachine->transition($ride, 'accept', $driver);
        $exception = $stateMachine->transition($accepted, 'flag_exception', $driver, ['reason' => 'flat_tire']);
        $stateMachine->transition($exception, 'reassign', null, ['reason' => 'exception_reassignment']);

        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();

        $this->assertDatabaseHas('group_messages', [
            'group_chat_id' => $chat->id,
            'content' => 'driverOld has left the group.',
        ]);
    }

    public function test_user_joined_notice_when_new_driver_assigned(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $oldDriver = User::factory()->create(['role' => 'driver', 'username' => 'driverOld']);
        $newDriver = User::factory()->create(['role' => 'driver', 'username' => 'driverNew']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $accepted = $stateMachine->transition($ride, 'accept', $oldDriver);
        $exception = $stateMachine->transition($accepted, 'flag_exception', $oldDriver, ['reason' => 'flat_tire']);
        $matching = $stateMachine->transition($exception, 'reassign', null, ['reason' => 'exception_reassignment']);
        $stateMachine->transition($matching, 'accept', $newDriver);

        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();

        $this->assertDatabaseHas('group_messages', [
            'group_chat_id' => $chat->id,
            'content' => 'driverNew has joined the group.',
        ]);
    }

    public function test_user_left_notice_when_no_show_reassignment_removes_driver(): void
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver', 'username' => 'driverNoShow']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $accepted = $stateMachine->transition($ride, 'accept', $driver);
        $stateMachine->transition($accepted, 'reassign', null, ['reason' => 'no_show_auto_revert']);

        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();

        $this->assertDatabaseHas('group_messages', [
            'group_chat_id' => $chat->id,
            'content' => 'driverNoShow has left the group.',
        ]);
    }
}
