<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_stock_inventory_decrements_on_purchase(): void
    {
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $buyer = User::factory()->create(['role' => 'driver']);

        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_published' => true]);

        $variant = $product->variants()->create([
            'sku' => 'SKU-LIVE-001',
            'label' => 'Live Unit',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 5,
        ]);

        $variant->pricingTiers()->create(['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 9.00]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertStatus(201);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_quantity' => 2,
        ]);
    }

    public function test_shared_inventory_is_pooled_across_shared_variants(): void
    {
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $buyer = User::factory()->create(['role' => 'rider']);

        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_published' => true]);

        $variantA = $product->variants()->create([
            'sku' => 'SKU-SHARED-A',
            'label' => 'Shared A',
            'inventory_strategy' => 'shared',
            'stock_quantity' => 1,
        ]);
        $variantB = $product->variants()->create([
            'sku' => 'SKU-SHARED-B',
            'label' => 'Shared B',
            'inventory_strategy' => 'shared',
            'stock_quantity' => 2,
        ]);

        $variantA->pricingTiers()->create(['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 12.00]);
        $variantB->pricingTiers()->create(['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 12.00]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'variant_id' => $variantA->id,
            'quantity' => 3,
        ])->assertStatus(201);

        $this->assertDatabaseHas('product_variants', ['id' => $variantA->id, 'stock_quantity' => 0]);
        $this->assertDatabaseHas('product_variants', ['id' => $variantB->id, 'stock_quantity' => 0]);
    }
}
