<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_total_uses_matching_tier(): void
    {
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_published' => true]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 100,
        ]);

        $variant->pricingTiers()->createMany([
            ['min_quantity' => 1, 'max_quantity' => 2, 'unit_price' => 20.00],
            ['min_quantity' => 3, 'max_quantity' => null, 'unit_price' => 15.50],
        ]);

        $total = app(PricingService::class)->calculateTotal($variant, 3);

        $this->assertSame(46.5, $total);
    }

    public function test_calculate_total_throws_when_no_tier_matches(): void
    {
        $seller = User::factory()->create(['role' => 'fleet_manager']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'is_published' => true]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'inventory_strategy' => 'live_stock',
            'stock_quantity' => 100,
        ]);

        $variant->pricingTiers()->create([
            'min_quantity' => 10,
            'max_quantity' => null,
            'unit_price' => 99.00,
        ]);

        $this->expectException(ValidationException::class);

        app(PricingService::class)->calculateTotal($variant, 2);
    }
}
