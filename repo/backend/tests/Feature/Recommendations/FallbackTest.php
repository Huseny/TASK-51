<?php

namespace Tests\Feature\Recommendations;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_fallback_returns_10_random_published_products(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Product::factory()->count(12)->create(['is_published' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/recommendations')
            ->assertStatus(200)
            ->assertJsonPath('fallback', true)
            ->assertJsonCount(10, 'data');
    }
}
