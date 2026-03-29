<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_create_product_with_variants_and_tiers(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/products', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('product.seller_id', $manager->id)
            ->assertJsonPath('product.variants.0.tiers.0.min_quantity', 1)
            ->assertJsonPath('product.variants.0.tiers.1.unit_price', '17.99');
    }

    public function test_owner_can_update_and_publish_and_delete_product(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $create = $this->postJson('/api/v1/products', $this->payload())->assertStatus(201);
        $productId = $create->json('product.id');
        $variantId = $create->json('product.variants.0.id');

        $updatePayload = $this->payload();
        $updatePayload['name'] = 'Updated Bundle';
        $updatePayload['variants'][0]['id'] = $variantId;
        $updatePayload['variants'][0]['sku'] = 'SKU-UPDATED-01';

        $this->putJson('/api/v1/products/'.$productId, $updatePayload)
            ->assertStatus(200)
            ->assertJsonPath('product.name', 'Updated Bundle')
            ->assertJsonPath('product.variants.0.sku', 'SKU-UPDATED-01');

        $this->patchJson('/api/v1/products/'.$productId.'/publish', ['is_published' => true])
            ->assertStatus(200)
            ->assertJsonPath('product.is_published', true);

        $this->deleteJson('/api/v1/products/'.$productId)
            ->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $productId]);
    }

    public function test_manager_index_includes_own_unpublished_products(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Product::factory()->create([
            'seller_id' => $manager->id,
            'is_published' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Energy Pack',
            'description' => 'Road snack bundle',
            'category' => 'snack',
            'tags' => ['energy', 'bundle'],
            'purchase_limit' => 5,
            'variants' => [
                [
                    'sku' => 'SKU-ENERGY-001',
                    'label' => 'Standard Box',
                    'inventory_strategy' => 'live_stock',
                    'stock_quantity' => 40,
                    'tiers' => [
                        ['min_quantity' => 1, 'max_quantity' => 2, 'unit_price' => 19.99],
                        ['min_quantity' => 3, 'max_quantity' => null, 'unit_price' => 17.99],
                    ],
                ],
            ],
        ];
    }
}
