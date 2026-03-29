<?php

namespace Tests\Feature\Security;

use App\Models\Product;
use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoundaryExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_email_and_massive_strings_and_negative_quantities_return_422(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'boundary_user',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'role' => 'rider',
            'email' => 'invalid-email-format',
        ])->assertStatus(422);

        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/ride-orders', [
            'origin_address' => str_repeat('A', 501),
            'destination_address' => '200 Main St',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
        ])->assertStatus(422);

        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_published' => true,
        ]);

        $variant = $product->variants()->create([
            'sku' => 'SKU-BND-01',
            'label' => 'base',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 10,
        ]);
        $variant->pricingTiers()->create([
            'min_quantity' => 1,
            'max_quantity' => null,
            'unit_price' => 9.99,
        ]);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variant->id,
            'quantity' => -1,
        ])->assertStatus(422);
    }

    public function test_double_accept_with_same_idempotency_key_is_safe(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'matching',
            'driver_id' => null,
        ]);

        Sanctum::actingAs($driver);

        $responseA = $this->withHeader('X-Idempotency-Key', 'RID-ACCEPT-KEY')
            ->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
                'action' => 'accept',
            ])
            ->assertStatus(200);

        $responseB = $this->withHeader('X-Idempotency-Key', 'RID-ACCEPT-KEY')
            ->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
                'action' => 'accept',
            ])
            ->assertStatus(200);

        $this->assertSame($responseA->json(), $responseB->json());
        $this->assertDatabaseHas('ride_orders', [
            'id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
        ]);
    }

    public function test_driver_start_before_time_window_start_currently_allowed(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = RideOrder::factory()->create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'time_window_start' => now()->addHour(),
            'time_window_end' => now()->addHours(2),
        ]);

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/ride-orders/'.$order->id.'/transition', [
            'action' => 'start',
        ])->assertStatus(200)
            ->assertJsonPath('order.status', 'in_progress');
    }
}
