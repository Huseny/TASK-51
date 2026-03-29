<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecommendationModel;
use App\Models\RecommendationResult;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function computeDailyModel(): RecommendationModel
    {
        return DB::transaction(function (): RecommendationModel {
            $nextVersion = ((int) RecommendationModel::query()->max('version')) + 1;

            $model = RecommendationModel::query()->create([
                'version' => $nextVersion,
                'is_active' => false,
                'feature_snapshot' => ['status' => 'building'],
                'created_at' => now(),
            ]);

            $products = Product::query()
                ->where('is_published', true)
                ->get(['id', 'seller_id', 'category', 'tags']);

            $globalCollabScores = UserInteraction::query()
                ->select('item_id', DB::raw('SUM(score) as total_score'))
                ->groupBy('item_id')
                ->pluck('total_score', 'item_id')
                ->map(fn ($value) => (float) $value)
                ->all();

            $maxCollab = ! empty($globalCollabScores)
                ? max($globalCollabScores)
                : 1.0;

            $normalizedCollab = [];
            foreach ($globalCollabScores as $itemId => $value) {
                $normalizedCollab[(int) $itemId] = $maxCollab > 0 ? ($value / $maxCollab) : 0.0;
            }

            $users = User::query()->get(['id']);
            $resultRows = [];

            foreach ($users as $user) {
                $recommendations = $this->recommendForUser($user, $products, $normalizedCollab);

                $rank = 1;
                foreach ($recommendations as $row) {
                    $resultRows[] = [
                        'model_version_id' => $model->id,
                        'user_id' => $user->id,
                        'item_id' => $row['item_id'],
                        'score' => $row['score'],
                        'rank_order' => $rank,
                        'is_exploration' => $row['is_exploration'],
                        'created_at' => now(),
                    ];
                    $rank++;
                }
            }

            if (! empty($resultRows)) {
                RecommendationResult::query()->insert($resultRows);
            }

            RecommendationModel::query()
                ->where('id', '!=', $model->id)
                ->update(['is_active' => false]);

            $model->is_active = true;
            $model->feature_snapshot = [
                'users_processed' => $users->count(),
                'published_items' => $products->count(),
                'results_generated' => count($resultRows),
                'collaborative_method' => 'global_popularity_weighted_by_interactions',
            ];
            $model->save();

            return $model;
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, float>  $normalizedCollab
     * @return array<int, array{item_id: int, score: float, is_exploration: bool}>
     */
    private function recommendForUser(User $user, Collection $products, array $normalizedCollab): array
    {
        $interactedIds = UserInteraction::query()
            ->where('user_id', $user->id)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $unseenProducts = $products
            ->reject(fn (Product $product) => in_array($product->id, $interactedIds, true))
            ->values();

        if ($unseenProducts->isEmpty()) {
            return [];
        }

        $categoryWeights = $this->userCategoryWeights($user);
        $tagWeights = $this->userTagWeights($user);

        $rawContentScores = [];
        foreach ($unseenProducts as $product) {
            $categoryScore = $categoryWeights[$product->category] ?? 0.0;
            $tagScore = 0.0;

            $tags = is_array($product->tags) ? $product->tags : [];
            foreach ($tags as $tag) {
                if (is_string($tag)) {
                    $tagScore += $tagWeights[$tag] ?? 0.0;
                }
            }

            $rawContentScores[$product->id] = $categoryScore + $tagScore;
        }

        $maxContent = ! empty($rawContentScores) ? max($rawContentScores) : 1.0;

        $finalScores = [];
        foreach ($unseenProducts as $product) {
            $collab = $normalizedCollab[$product->id] ?? 0.0;
            $content = $maxContent > 0 ? (($rawContentScores[$product->id] ?? 0.0) / $maxContent) : 0.0;
            $finalScores[$product->id] = round(($collab * 0.6) + ($content * 0.4), 6);
        }

        arsort($finalScores);

        $unseenIds = $unseenProducts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $explorationItemId = $unseenIds[array_rand($unseenIds)];
        $explorationScore = (float) ($finalScores[$explorationItemId] ?? 0.0);

        $sellerCounts = [];
        $explorationProduct = $unseenProducts->firstWhere('id', $explorationItemId);
        if ($explorationProduct instanceof Product) {
            $sellerCounts[$explorationProduct->seller_id] = 1;
        }

        unset($finalScores[$explorationItemId]);

        $selected = [];
        foreach ($finalScores as $itemId => $score) {
            if (count($selected) >= 9) {
                break;
            }

            $product = $unseenProducts->firstWhere('id', $itemId);
            if (! $product instanceof Product) {
                continue;
            }

            $sellerCount = $sellerCounts[$product->seller_id] ?? 0;
            if ($sellerCount >= 2) {
                continue;
            }

            $sellerCounts[$product->seller_id] = $sellerCount + 1;
            $selected[] = [
                'item_id' => (int) $itemId,
                'score' => (float) $score,
                'is_exploration' => false,
            ];
        }

        $explorationRow = [
            'item_id' => (int) $explorationItemId,
            'score' => $explorationScore,
            'is_exploration' => true,
        ];

        $insertPosition = count($selected) > 0 ? random_int(0, count($selected)) : 0;
        array_splice($selected, $insertPosition, 0, [$explorationRow]);

        return array_slice($selected, 0, 10);
    }

    /**
     * @return array<string, float>
     */
    private function userCategoryWeights(User $user): array
    {
        return UserInteraction::query()
            ->join('products', 'products.id', '=', 'user_interactions.item_id')
            ->where('user_interactions.user_id', $user->id)
            ->select('products.category', DB::raw('SUM(user_interactions.score) as score_sum'))
            ->groupBy('products.category')
            ->pluck('score_sum', 'products.category')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function userTagWeights(User $user): array
    {
        $rows = UserInteraction::query()
            ->join('products', 'products.id', '=', 'user_interactions.item_id')
            ->where('user_interactions.user_id', $user->id)
            ->select('products.tags', 'user_interactions.score')
            ->get();

        $weights = [];
        foreach ($rows as $row) {
            $tags = [];

            if (is_string($row->tags)) {
                $decoded = json_decode($row->tags, true);
                $tags = is_array($decoded) ? $decoded : [];
            } elseif (is_array($row->tags)) {
                $tags = $row->tags;
            }

            foreach ($tags as $tag) {
                if (! is_string($tag)) {
                    continue;
                }

                $weights[$tag] = ($weights[$tag] ?? 0.0) + (float) $row->score;
            }
        }

        return $weights;
    }
}
