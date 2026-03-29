<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TieredPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_uses_matching_tiered_price(): void
    {
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $buyer = User::factory()->create(['role' => 'rider']);

        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'is_published' => true,
        ]);

        $variant = $product->variants()->create([
            'sku' => 'SKU-TIER-001',
            'label' => 'Tiered Pack',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 100,
        ]);

        $variant->pricingTiers()->createMany([
            ['min_quantity' => 1, 'max_quantity' => 2, 'unit_price' => 19.99],
            ['min_quantity' => 3, 'max_quantity' => null, 'unit_price' => 17.99],
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertStatus(201)
            ->assertJsonPath('purchase.total_price', '53.97');
    }
}
