<?php

namespace Tests\Feature\Recommendations;

use App\Jobs\ComputeRecommendations;
use App\Models\Product;
use App\Models\RecommendationFeatureSet;
use App\Models\RecommendationFeatureValue;
use App\Models\RecommendationModel;
use App\Models\RecommendationResult;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_job_enforces_diversity_and_persists_epsilon_metadata(): void
    {
        config()->set('roadlink.recommendations.epsilon', 1.0);

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
        $this->assertGreaterThan(0, $results->where('is_exploration', true)->count());
        $this->assertSame('epsilon_greedy', $firstModel->feature_snapshot['policy']);
        $this->assertSame(1.0, (float) $firstModel->feature_snapshot['epsilon']);
        $this->assertSame(2, (int) $firstModel->feature_snapshot['max_items_per_seller']);
        $this->assertSame($firstModel->version, (int) $firstModel->feature_snapshot['feature_version']);

        $featureSet = RecommendationFeatureSet::query()
            ->where('recommendation_model_id', $firstModel->id)
            ->firstOrFail();

        $this->assertDatabaseHas('recommendation_feature_values', [
            'feature_set_id' => $featureSet->id,
            'feature_key' => 'category_weights',
            'user_id' => $userA->id,
        ]);
        $this->assertDatabaseHas('recommendation_feature_values', [
            'feature_set_id' => $featureSet->id,
            'feature_key' => 'normalized_collab',
        ]);
        $this->assertSame(10, RecommendationResult::query()->where('feature_set_id', $featureSet->id)->count());

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

    public function test_epsilon_can_disable_exploration_when_set_to_zero(): void
    {
        config()->set('roadlink.recommendations.epsilon', 0.0);

        $user = User::factory()->create(['role' => 'rider']);
        $sellers = User::factory()->count(5)->create(['role' => 'fleet_manager']);

        for ($i = 0; $i < 20; $i++) {
            Product::factory()->create([
                'seller_id' => $sellers[$i % 5]->id,
                'is_published' => true,
                'category' => 'snack',
                'tags' => ['energy'],
            ]);
        }

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'item_id' => Product::query()->firstOrFail()->id,
            'interaction_type' => 'view',
            'score' => 1.0,
            'created_at' => now(),
        ]);

        ComputeRecommendations::dispatchSync();

        $model = RecommendationModel::query()->where('is_active', true)->firstOrFail();

        $this->assertSame(0.0, (float) $model->feature_snapshot['epsilon']);

        $this->assertSame(
            0,
            RecommendationResult::query()
                ->where('model_version_id', $model->id)
                ->where('user_id', $user->id)
                ->where('is_exploration', true)
                ->count()
        );
    }

    public function test_recommendations_can_be_replayed_from_saved_feature_set(): void
    {
        config()->set('roadlink.recommendations.epsilon', 0.0);

        $user = User::factory()->create(['role' => 'rider']);
        $seller = User::factory()->create(['role' => 'fleet_manager']);

        $products = Product::factory()->count(5)->create([
            'seller_id' => $seller->id,
            'is_published' => true,
            'category' => 'gear',
            'tags' => ['road', 'safety'],
        ]);

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'item_id' => $products->first()->id,
            'interaction_type' => 'purchase',
            'score' => 5.0,
            'created_at' => now(),
        ]);

        ComputeRecommendations::dispatchSync();

        $model = RecommendationModel::query()->where('is_active', true)->firstOrFail();
        $featureSet = $model->featureSet()->firstOrFail();

        $stored = RecommendationResult::query()
            ->where('model_version_id', $model->id)
            ->where('user_id', $user->id)
            ->orderBy('rank_order')
            ->get(['item_id', 'score', 'is_exploration'])
            ->map(fn (RecommendationResult $result) => [
                'item_id' => $result->item_id,
                'score' => $result->score,
                'is_exploration' => $result->is_exploration,
            ])
            ->all();

        $replayed = app(\App\Services\RecommendationService::class)
            ->replayRecommendationsFromFeatureSet($featureSet, $user);

        $this->assertSame($stored, $replayed);
        $this->assertGreaterThan(
            0,
            RecommendationFeatureValue::query()->where('feature_set_id', $featureSet->id)->count()
        );
    }
}
