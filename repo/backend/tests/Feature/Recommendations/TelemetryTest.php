<?php

namespace Tests\Feature\Recommendations;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_interactions_logs_view_and_purchase_scores(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        $product = Product::factory()->create(['is_published' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/interactions', [
            'item_id' => $product->id,
            'interaction_type' => 'view',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $user->id,
            'item_id' => $product->id,
            'interaction_type' => 'view',
            'score' => 1,
        ]);

        $this->postJson('/api/v1/interactions', [
            'item_id' => $product->id,
            'interaction_type' => 'purchase',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $user->id,
            'item_id' => $product->id,
            'interaction_type' => 'purchase',
            'score' => 5,
        ]);
    }
}
