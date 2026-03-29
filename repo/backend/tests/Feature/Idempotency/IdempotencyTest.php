<?php

namespace Tests\Feature\Idempotency;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_same_idempotency_key_replays_original_response_and_new_key_creates_new_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 12:00:00'));

        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payloadA = [
            'origin_address' => '100 North Rd',
            'destination_address' => '200 Center Ave',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'First request',
        ];

        $responseOne = $this->withHeader('X-Idempotency-Key', 'KEY-A')
            ->postJson('/api/v1/ride-orders', $payloadA)
            ->assertStatus(201);

        $payloadChanged = [
            'origin_address' => '999 Changed Address',
            'destination_address' => '888 Changed Destination',
            'rider_count' => 1,
            'time_window_start' => now()->addHours(3)->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(4)->format('Y-m-d H:i'),
            'notes' => 'Should be ignored',
        ];

        $responseTwo = $this->withHeader('X-Idempotency-Key', 'KEY-A')
            ->postJson('/api/v1/ride-orders', $payloadChanged)
            ->assertStatus(201);

        $this->assertSame($responseOne->json(), $responseTwo->json());
        $this->assertDatabaseCount('ride_orders', 1);

        $responseThree = $this->withHeader('X-Idempotency-Key', 'KEY-B')
            ->postJson('/api/v1/ride-orders', $payloadA)
            ->assertStatus(201);

        $this->assertNotSame(
            $responseOne->json('order.id'),
            $responseThree->json('order.id')
        );
        $this->assertDatabaseCount('ride_orders', 2);
    }
}
