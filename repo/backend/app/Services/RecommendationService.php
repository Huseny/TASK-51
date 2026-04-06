<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecommendationFeatureSet;
use App\Models\RecommendationFeatureValue;
use App\Models\RecommendationModel;
use App\Models\RecommendationResult;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    private const TOP_K = 10;
    private const FEATURE_SCHEMA_VERSION = 1;

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

            $featureSet = RecommendationFeatureSet::query()->create([
                'recommendation_model_id' => $model->id,
                'version' => $nextVersion,
                'schema_version' => self::FEATURE_SCHEMA_VERSION,
                'seed' => $this->seedForVersion($nextVersion),
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

            $this->persistGlobalFeatureRows($featureSet, $normalizedCollab);

            $users = User::query()->get(['id']);
            $resultRows = [];
            $featureRows = [];

            foreach ($users as $user) {
                $categoryWeights = $this->userCategoryWeights($user);
                $tagWeights = $this->userTagWeights($user);

                $featureRows[] = [
                    'feature_set_id' => $featureSet->id,
                    'user_id' => $user->id,
                    'item_id' => null,
                    'feature_key' => 'category_weights',
                    'feature_value' => json_encode($categoryWeights, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ];
                $featureRows[] = [
                    'feature_set_id' => $featureSet->id,
                    'user_id' => $user->id,
                    'item_id' => null,
                    'feature_key' => 'tag_weights',
                    'feature_value' => json_encode($tagWeights, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ];

                $recommendations = $this->recommendForUser(
                    $user,
                    $products,
                    $normalizedCollab,
                    $categoryWeights,
                    $tagWeights,
                    $featureSet,
                    $featureRows,
                );

                $rank = 1;
                foreach ($recommendations as $row) {
                    $resultRows[] = [
                        'model_version_id' => $model->id,
                        'feature_set_id' => $featureSet->id,
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

            if (! empty($featureRows)) {
                RecommendationFeatureValue::query()->insert($featureRows);
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
                'feature_version' => $featureSet->version,
                'feature_schema_version' => $featureSet->schema_version,
                'seed' => $featureSet->seed,
            ];
            $model->save();

            return $model;
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, float>  $normalizedCollab
     * @param  array<string, float>  $categoryWeights
     * @param  array<string, float>  $tagWeights
     * @param  array<int, array<string, mixed>>  $featureRows
     * @return array<int, array{item_id: int, score: float, is_exploration: bool}>
     */
    private function recommendForUser(
        User $user,
        Collection $products,
        array $normalizedCollab,
        array $categoryWeights,
        array $tagWeights,
        RecommendationFeatureSet $featureSet,
        array &$featureRows,
    ): array
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

        return $this->selectTopKWithEpsilon(
            $unseenProducts,
            $finalScores,
            $normalizedCollab,
            $rawContentScores,
            $maxContent,
            $user->id,
            $featureSet,
            $featureRows,
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, float>  $finalScores
     * @param  array<int, float>  $normalizedCollab
     * @param  array<int, float>  $rawContentScores
     * @param  array<int, array<string, mixed>>  $featureRows
     * @return array<int, array{item_id: int, score: float, is_exploration: bool}>
     */
    private function selectTopKWithEpsilon(
        Collection $products,
        array $finalScores,
        array $normalizedCollab,
        array $rawContentScores,
        float $maxContent,
        int $userId,
        RecommendationFeatureSet $featureSet,
        array &$featureRows,
    ): array
    {
        $epsilon = $this->epsilon();
        $maxPerSeller = $this->maxItemsPerSeller();

        $available = collect($finalScores)
            ->map(fn (float $score, int $itemId): array => ['item_id' => $itemId, 'score' => $score])
            ->sortByDesc('score')
            ->values();

        $selected = [];
        $sellerCounts = [];
        $iteration = 0;

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

            $isExploration = $this->deterministicFloat($featureSet->seed, $userId, $iteration) < $epsilon;

            $picked = $isExploration
                ? $allowedCandidates[$this->deterministicIndex($featureSet->seed, $userId, $iteration, $allowedCandidates->count())]
                : $allowedCandidates->sortByDesc('score')->first();

            $product = $products->firstWhere('id', $picked['item_id']);
            if (! $product instanceof Product) {
                $available = $available->reject(fn (array $row): bool => $row['item_id'] === $picked['item_id'])->values();
                continue;
            }

            $sellerCounts[$product->seller_id] = ($sellerCounts[$product->seller_id] ?? 0) + 1;
            $contentScore = $maxContent > 0 ? (($rawContentScores[$product->id] ?? 0.0) / $maxContent) : 0.0;
            $selected[] = [
                'item_id' => (int) $picked['item_id'],
                'score' => (float) $picked['score'],
                'is_exploration' => $isExploration,
            ];

            $featureRows[] = [
                'feature_set_id' => $featureSet->id,
                'user_id' => $userId,
                'item_id' => (int) $picked['item_id'],
                'feature_key' => 'result_inputs',
                'feature_value' => json_encode([
                    'collaborative_score' => (float) ($normalizedCollab[$product->id] ?? 0.0),
                    'content_score' => (float) $contentScore,
                    'final_score' => (float) $picked['score'],
                    'is_exploration' => $isExploration,
                    'selection_step' => $iteration,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ];

            $available = $available->reject(fn (array $row): bool => $row['item_id'] === $picked['item_id'])->values();
            $iteration++;
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

    /**
     * @return array<int, array{item_id: int, score: float, is_exploration: bool}>
     */
    public function replayRecommendationsFromFeatureSet(RecommendationFeatureSet $featureSet, User $user): array
    {
        $products = Product::query()
            ->where('is_published', true)
            ->get(['id', 'seller_id', 'category', 'tags']);

        $normalizedCollab = RecommendationFeatureValue::query()
            ->where('feature_set_id', $featureSet->id)
            ->where('feature_key', 'normalized_collab')
            ->get()
            ->mapWithKeys(fn (RecommendationFeatureValue $value) => [(int) $value->item_id => (float) ($value->feature_value['score'] ?? 0.0)])
            ->all();

        $categoryWeights = RecommendationFeatureValue::query()
            ->where('feature_set_id', $featureSet->id)
            ->where('user_id', $user->id)
            ->where('feature_key', 'category_weights')
            ->first()?->feature_value ?? [];

        $tagWeights = RecommendationFeatureValue::query()
            ->where('feature_set_id', $featureSet->id)
            ->where('user_id', $user->id)
            ->where('feature_key', 'tag_weights')
            ->first()?->feature_value ?? [];

        $discard = [];

        return $this->recommendForUser(
            $user,
            $products,
            $normalizedCollab,
            is_array($categoryWeights) ? $categoryWeights : [],
            is_array($tagWeights) ? $tagWeights : [],
            $featureSet,
            $discard,
        );
    }

    /**
     * @param  array<int, float>  $normalizedCollab
     */
    private function persistGlobalFeatureRows(RecommendationFeatureSet $featureSet, array $normalizedCollab): void
    {
        if (empty($normalizedCollab)) {
            return;
        }

        $rows = collect($normalizedCollab)->map(fn (float $score, int $itemId) => [
            'feature_set_id' => $featureSet->id,
            'user_id' => null,
            'item_id' => $itemId,
            'feature_key' => 'normalized_collab',
            'feature_value' => json_encode(['score' => $score], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ])->all();

        RecommendationFeatureValue::query()->insert($rows);
    }

    private function seedForVersion(int $version): int
    {
        return crc32(sprintf('recommendation-feature-set-%d', $version));
    }

    private function deterministicFloat(int $seed, int $userId, int $iteration): float
    {
        $hash = hash('sha256', sprintf('%d|%d|%d|float', $seed, $userId, $iteration));
        $slice = substr($hash, 0, 8);

        return hexdec($slice) / 0xFFFFFFFF;
    }

    private function deterministicIndex(int $seed, int $userId, int $iteration, int $count): int
    {
        if ($count <= 1) {
            return 0;
        }

        $hash = hash('sha256', sprintf('%d|%d|%d|index', $seed, $userId, $iteration));
        return hexdec(substr($hash, 0, 8)) % $count;
    }
}
