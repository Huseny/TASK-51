<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_purchase_limit_per_user_per_day_is_enforced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 10:00:00'));

        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $buyer = User::factory()->create(['role' => 'driver']);

        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_published' => true,
            'purchase_limit_per_user_per_day' => 3,
        ]);

        $variant = $product->variants()->create([
            'sku' => 'SKU-LIMIT-001',
            'label' => 'Daily Pack',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 100,
        ]);

        $variant->pricingTiers()->create([
            'min_quantity' => 1,
            'max_quantity' => null,
            'unit_price' => 10.00,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertStatus(201);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('details.quantity.0', 'Daily purchase limit exceeded for this product.');
    }
}
