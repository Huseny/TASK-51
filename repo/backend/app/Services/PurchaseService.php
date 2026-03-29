<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    public function checkDailyLimit(User $user, Product $product, int $quantity): void
    {
        $limit = $product->purchase_limit_per_user_per_day;
        if ($limit === null) {
            return;
        }

        $todayQuantity = PurchaseRecord::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->whereBetween('purchased_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('quantity');

        if (($todayQuantity + $quantity) > $limit) {
            throw ValidationException::withMessages([
                'quantity' => ['Daily purchase limit exceeded for this product.'],
            ]);
        }
    }

    public function purchase(User $user, Product $product, int $variantId, int $quantity): PurchaseRecord
    {
        return DB::transaction(function () use ($user, $product, $variantId, $quantity): PurchaseRecord {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ProductVariant $lockedVariant */
            $lockedVariant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->checkDailyLimit($user, $lockedProduct, $quantity);

            $totalPrice = $this->pricingService->calculateTotal($lockedVariant, $quantity);
            $this->inventoryService->checkAndDecrement($lockedProduct, $lockedVariant, $quantity);

            return PurchaseRecord::query()->create([
                'user_id' => $user->id,
                'product_id' => $lockedProduct->id,
                'variant_id' => $lockedVariant->id,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'purchased_at' => now(),
            ]);
        });
    }
}
