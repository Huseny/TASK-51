<?php

namespace Tests\Feature\Idempotency;

use App\Models\User;
use Illuminate\Support\Str;
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

    public function test_same_key_for_different_user_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 12:00:00'));

        $riderA = User::factory()->create(['role' => 'rider']);
        $riderB = User::factory()->create(['role' => 'rider']);

        $payload = [
            'origin_address' => '100 North Rd',
            'destination_address' => '200 Center Ave',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'Same key, different rider',
        ];

        Sanctum::actingAs($riderA);
        $this->withHeader('X-Idempotency-Key', 'KEY-SHARED')
            ->postJson('/api/v1/ride-orders', $payload)
            ->assertCreated();

        Sanctum::actingAs($riderB);
        $this->withHeader('X-Idempotency-Key', 'KEY-SHARED')
            ->postJson('/api/v1/ride-orders', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_scope_conflict');

        $this->assertDatabaseCount('ride_orders', 1);
    }

    public function test_same_key_same_scope_with_different_body_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 12:00:00'));

        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $payload = [
            'origin_address' => '100 North Rd',
            'destination_address' => '200 Center Ave',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'Original request',
        ];

        $this->withHeader('X-Idempotency-Key', 'KEY-HASH')
            ->postJson('/api/v1/ride-orders', $payload)
            ->assertCreated();

        $this->withHeader('X-Idempotency-Key', 'KEY-HASH')
            ->postJson('/api/v1/ride-orders', [
                ...$payload,
                'notes' => 'Changed payload '.Str::random(6),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_payload_mismatch');

        $this->assertDatabaseCount('ride_orders', 1);
    }
}
