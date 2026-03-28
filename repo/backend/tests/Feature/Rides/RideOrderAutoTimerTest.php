<?php

namespace Tests\Feature\Rides;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RideOrderAutoTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_matching_for_eleven_minutes_is_auto_canceled(): void
    {
        Carbon::setTestNow('2026-03-25 10:00:00');
        $rider = User::factory()->create(['role' => 'rider']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'matching',
            'created_at' => now()->subMinutes(11),
        ]);

        $this->artisan('ride:auto-cancel-unmatched')->assertSuccessful();

        $this->assertSame('canceled', $order->fresh()->status);
    }

    public function test_matching_for_eight_minutes_is_not_auto_canceled(): void
    {
        Carbon::setTestNow('2026-03-25 10:00:00');
        $rider = User::factory()->create(['role' => 'rider']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'matching',
            'created_at' => now()->subMinutes(8),
        ]);

        $this->artisan('ride:auto-cancel-unmatched')->assertSuccessful();

        $this->assertSame('matching', $order->fresh()->status);
    }

    public function test_accepted_for_six_minutes_without_start_is_auto_reverted(): void
    {
        Carbon::setTestNow('2026-03-25 10:00:00');
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinutes(6),
            'started_at' => null,
        ]);

        $this->artisan('ride:auto-revert-no-show')->assertSuccessful();

        $this->assertSame('matching', $order->fresh()->status);
        $this->assertNull($order->fresh()->driver_id);
    }

    public function test_accepted_for_three_minutes_is_not_reverted(): void
    {
        Carbon::setTestNow('2026-03-25 10:00:00');
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinutes(3),
            'started_at' => null,
        ]);

        $this->artisan('ride:auto-revert-no-show')->assertSuccessful();

        $this->assertSame('accepted', $order->fresh()->status);
    }

    public function test_started_order_is_not_reverted_regardless_of_time(): void
    {
        Carbon::setTestNow('2026-03-25 10:00:00');
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(19),
        ]);

        $this->artisan('ride:auto-revert-no-show')->assertSuccessful();

        $this->assertSame('accepted', $order->fresh()->status);
    }
}
