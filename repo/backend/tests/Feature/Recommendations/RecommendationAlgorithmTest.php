<?php

namespace Tests\Feature\Recommendations;

use App\Jobs\ComputeRecommendations;
use App\Models\Product;
use App\Models\RecommendationModel;
use App\Models\RecommendationResult;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_job_enforces_diversity_and_epsilon_and_versioning(): void
    {
        $userA = User::factory()->create(['role' => 'rider']);
        $userB = User::factory()->create(['role' => 'rider']);

        $sellers = User::factory()->count(6)->create(['role' => 'fleet_manager']);

        $products = collect();
        for ($i = 0; $i < 20; $i++) {
            $products->push(Product::factory()->create([
                'seller_id' => $sellers[$i % 6]->id,
                'is_published' => true,
                'category' => $i % 2 === 0 ? 'snack' : 'gear',
                'tags' => $i % 2 === 0 ? ['energy', 'road'] : ['safety', 'road'],
            ]));
        }

        foreach ($products->take(3) as $item) {
            UserInteraction::query()->create([
                'user_id' => $userA->id,
                'item_id' => $item->id,
                'interaction_type' => 'purchase',
                'score' => 5.0,
                'created_at' => now(),
            ]);
        }

        foreach ($products->slice(3, 4) as $item) {
            UserInteraction::query()->create([
                'user_id' => $userB->id,
                'item_id' => $item->id,
                'interaction_type' => 'purchase',
                'score' => 5.0,
                'created_at' => now(),
            ]);
        }

        ComputeRecommendations::dispatchSync();

        $firstModel = RecommendationModel::query()->where('is_active', true)->firstOrFail();

        $results = RecommendationResult::query()
            ->where('model_version_id', $firstModel->id)
            ->where('user_id', $userA->id)
            ->orderBy('rank_order')
            ->get();

        $this->assertCount(10, $results);
        $this->assertSame(1, $results->where('is_exploration', true)->count());

        $sellerCounts = [];
        foreach ($results as $result) {
            $product = Product::query()->findOrFail($result->item_id);
            $sellerCounts[$product->seller_id] = ($sellerCounts[$product->seller_id] ?? 0) + 1;
        }

        foreach ($sellerCounts as $count) {
            $this->assertLessThanOrEqual(2, $count);
        }

        ComputeRecommendations::dispatchSync();

        $secondModel = RecommendationModel::query()->where('is_active', true)->firstOrFail();
        $this->assertNotSame($firstModel->id, $secondModel->id);
        $this->assertDatabaseHas('recommendation_models', [
            'id' => $firstModel->id,
            'is_active' => false,
        ]);
    }
}
