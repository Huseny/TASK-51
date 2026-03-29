<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductPublishRequest;
use App\Http\Requests\Products\ProductPurchaseRequest;
use App\Http\Requests\Products\ProductRequest;
use App\Http\Requests\Products\ProductUpdateRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private readonly PurchaseService $purchaseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with(['variants.pricingTiers'])
            ->orderByDesc('created_at');

        if (! $request->user() || $request->user()->role !== 'admin') {
            $query->where(function ($scoped) use ($request): void {
                $scoped->where('is_published', true);

                if ($request->user()) {
                    $scoped->orWhere('seller_id', $request->user()->id);
                }
            });
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $request->query('category'));
        }

        if ($request->filled('q')) {
            $term = (string) $request->query('q');
            $query->where(function ($subQuery) use ($term): void {
                $subQuery->where('name', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
            });
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if (! $product->is_published && ! $this->canManageProduct($request->user()->id, $request->user()->role, $product)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this product',
            ], 403);
        }

        return response()->json([
            'product' => $product->load(['variants.pricingTiers']),
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $payload = $request->validated();

        /** @var Product $product */
        $product = DB::transaction(function () use ($payload, $request): Product {
            $product = Product::query()->create([
                'seller_id' => $request->user()->id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'],
                'tags' => $payload['tags'] ?? null,
                'purchase_limit_per_user_per_day' => $payload['purchase_limit'] ?? null,
                'is_published' => false,
            ]);

            $this->syncVariants($product, $payload['variants']);

            return $product;
        });

        return response()->json([
            'product' => $product->load(['variants.pricingTiers']),
        ], 201);
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        if (! $this->canManageProduct($request->user()->id, $request->user()->role, $product)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to update this product',
            ], 403);
        }

        $payload = $request->validated();

        DB::transaction(function () use ($payload, $product): void {
            $product->fill([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'],
                'tags' => $payload['tags'] ?? null,
                'purchase_limit_per_user_per_day' => $payload['purchase_limit'] ?? null,
            ])->save();

            $this->syncVariants($product, $payload['variants']);
        });

        return response()->json([
            'product' => $product->fresh()->load(['variants.pricingTiers']),
        ]);
    }

    public function publish(ProductPublishRequest $request, Product $product): JsonResponse
    {
        if (! $this->canManageProduct($request->user()->id, $request->user()->role, $product)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to publish this product',
            ], 403);
        }

        $product->is_published = (bool) $request->validated('is_published');
        $product->save();

        return response()->json([
            'product' => $product->load(['variants.pricingTiers']),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if (! $this->canManageProduct($request->user()->id, $request->user()->role, $product)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to delete this product',
            ], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    public function purchase(ProductPurchaseRequest $request, Product $product): JsonResponse
    {
        if (! $product->is_published) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Product is not published',
                'details' => (object) [],
            ], 422);
        }

        $purchase = $this->purchaseService->purchase(
            $request->user(),
            $product,
            (int) $request->validated('variant_id'),
            (int) $request->validated('quantity'),
        );

        return response()->json([
            'purchase' => $purchase,
        ], 201);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptVariantIds = [];

        foreach ($variants as $variantPayload) {
            $variant = null;
            if (! empty($variantPayload['id'])) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('id', (int) $variantPayload['id'])
                    ->first();
            }

            if ($variant) {
                $variant->fill([
                    'sku' => $variantPayload['sku'],
                    'label' => $variantPayload['label'],
                    'inventory_strategy' => $variantPayload['inventory_strategy'],
                    'stock_quantity' => $variantPayload['stock_quantity'],
                    'presale_available_date' => $variantPayload['presale_available_date'] ?? null,
                ])->save();
            } else {
                $variant = $product->variants()->create([
                    'sku' => $variantPayload['sku'],
                    'label' => $variantPayload['label'],
                    'inventory_strategy' => $variantPayload['inventory_strategy'],
                    'stock_quantity' => $variantPayload['stock_quantity'],
                    'presale_available_date' => $variantPayload['presale_available_date'] ?? null,
                ]);
            }

            $keptVariantIds[] = $variant->id;

            $variant->pricingTiers()->delete();
            foreach ($variantPayload['tiers'] as $tier) {
                $variant->pricingTiers()->create([
                    'min_quantity' => $tier['min_quantity'],
                    'max_quantity' => $tier['max_quantity'] ?? null,
                    'unit_price' => $tier['unit_price'],
                ]);
            }
        }

        $product->variants()
            ->whereNotIn('id', $keptVariantIds)
            ->delete();
    }

    private function canManageProduct(int $userId, string $role, Product $product): bool
    {
        return $role === 'admin' || $product->seller_id === $userId;
    }
}
