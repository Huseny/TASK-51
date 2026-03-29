<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_cannot_create_product(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/products', [
            'name' => 'Not Allowed',
            'category' => 'gear',
            'variants' => [],
        ])->assertStatus(403);
    }

    public function test_non_owner_fleet_manager_cannot_update_other_managers_product(): void
    {
        $owner = User::factory()->create(['role' => 'fleet_manager']);
        $other = User::factory()->create(['role' => 'fleet_manager']);

        $product = Product::factory()->create(['seller_id' => $owner->id]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-AUTH-001',
            'label' => 'Auth Variant',
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 5,
        ]);
        $variant->pricingTiers()->create(['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 8.00]);

        Sanctum::actingAs($other);

        $this->putJson('/api/v1/products/'.$product->id, [
            'name' => 'Blocked Update',
            'description' => 'Nope',
            'category' => 'gear',
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => 'SKU-AUTH-001',
                    'label' => 'Auth Variant',
                    'inventory_strategy' => 'live_stock',
                    'stock_quantity' => 5,
                    'tiers' => [
                        ['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 8.00],
                    ],
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_unpublished_product_is_hidden_from_rider_but_visible_to_admin(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $rider = User::factory()->create(['role' => 'rider']);
        $admin = User::factory()->create(['role' => 'admin']);

        $product = Product::factory()->create([
            'seller_id' => $manager->id,
            'is_published' => false,
        ]);

        Sanctum::actingAs($rider);
        $this->getJson('/api/v1/products/'.$product->id)->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/products/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('product.id', $product->id);
    }
}
