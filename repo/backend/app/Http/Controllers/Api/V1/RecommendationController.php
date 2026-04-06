<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RecommendationModel;
use App\Models\RecommendationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeModel = RecommendationModel::query()
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if (! $activeModel) {
            return response()->json([
                'data' => $this->fallbackItems(),
                'model_version' => null,
                'feature_version' => null,
                'fallback' => true,
            ]);
        }

        $results = RecommendationResult::query()
            ->where('model_version_id', $activeModel->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('rank_order')
            ->with(['item'])
            ->get();

        if ($results->isEmpty()) {
            return response()->json([
                'data' => $this->fallbackItems(),
                'model_version' => $activeModel->version,
                'feature_version' => $activeModel->featureSet?->version,
                'fallback' => true,
            ]);
        }

        $data = $results
            ->filter(fn (RecommendationResult $result) => $result->item !== null && $result->item->is_published)
            ->map(fn (RecommendationResult $result): array => [
                'item' => $result->item,
                'score' => $result->score,
                'rank_order' => $result->rank_order,
                'is_exploration' => $result->is_exploration,
            ])
            ->values();

        return response()->json([
            'data' => $data,
            'model_version' => $activeModel->version,
            'feature_version' => $activeModel->featureSet?->version,
            'fallback' => false,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackItems(): array
    {
        return Product::query()
            ->where('is_published', true)
            ->inRandomOrder()
            ->limit(10)
            ->get()
            ->values()
            ->map(fn (Product $product, int $index): array => [
                'item' => $product,
                'score' => 0.0,
                'rank_order' => $index + 1,
                'is_exploration' => false,
            ])
            ->all();
    }
}
