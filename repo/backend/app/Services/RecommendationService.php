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
    private const TOP_K = 10;

    private const SCORE_WEIGHT_COLLAB = 0.6;

    private const SCORE_WEIGHT_CONTENT = 0.4;

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
                'policy' => 'epsilon_greedy',
                'epsilon' => $this->epsilon(),
                'max_items_per_seller' => $this->maxItemsPerSeller(),
                'top_k' => self::TOP_K,
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
            $finalScores[$product->id] = round(
                ($collab * self::SCORE_WEIGHT_COLLAB) + ($content * self::SCORE_WEIGHT_CONTENT),
                6
            );
        }

        return $this->selectTopKWithEpsilon($unseenProducts, $finalScores);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, float>  $finalScores
     * @return array<int, array{item_id: int, score: float, is_exploration: bool}>
     */
    private function selectTopKWithEpsilon(Collection $products, array $finalScores): array
    {
        $epsilon = $this->epsilon();
        $maxPerSeller = $this->maxItemsPerSeller();

        $available = collect($finalScores)
            ->map(fn (float $score, int $itemId): array => ['item_id' => $itemId, 'score' => $score])
            ->sortByDesc('score')
            ->values();

        $selected = [];
        $sellerCounts = [];

        while (count($selected) < self::TOP_K && $available->isNotEmpty()) {
            $allowedCandidates = $available
                ->filter(function (array $candidate) use ($products, $sellerCounts, $maxPerSeller): bool {
                    $product = $products->firstWhere('id', $candidate['item_id']);
                    if (! $product instanceof Product) {
                        return false;
                    }

                    return ($sellerCounts[$product->seller_id] ?? 0) < $maxPerSeller;
                })
                ->values();

            if ($allowedCandidates->isEmpty()) {
                break;
            }

            $isExploration = $this->randomFloat() < $epsilon;

            $picked = $isExploration
                ? $allowedCandidates->random()
                : $allowedCandidates->sortByDesc('score')->first();

            $product = $products->firstWhere('id', $picked['item_id']);
            if (! $product instanceof Product) {
                $available = $available->reject(fn (array $row): bool => $row['item_id'] === $picked['item_id'])->values();
                continue;
            }

            $sellerCounts[$product->seller_id] = ($sellerCounts[$product->seller_id] ?? 0) + 1;
            $selected[] = [
                'item_id' => (int) $picked['item_id'],
                'score' => (float) $picked['score'],
                'is_exploration' => $isExploration,
            ];

            $available = $available->reject(fn (array $row): bool => $row['item_id'] === $picked['item_id'])->values();
        }

        return $selected;
    }

    private function epsilon(): float
    {
        return max(0.0, min(1.0, (float) config('roadlink.recommendations.epsilon', 0.10)));
    }

    private function maxItemsPerSeller(): int
    {
        return max(1, (int) config('roadlink.recommendations.max_items_per_seller', 2));
    }

    private function randomFloat(): float
    {
        return mt_rand() / mt_getrandmax();
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
