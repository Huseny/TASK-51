<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function checkAndDecrement(Product $product, ProductVariant $variant, int $quantity): void
    {
        if ($variant->inventory_strategy === 'presale') {
            return;
        }

        if ($variant->inventory_strategy === 'live_stock') {
            $this->decrementLiveStock($variant, $quantity);

            return;
        }

        $this->decrementSharedStock($product, $variant, $quantity);
    }

    private function decrementLiveStock(ProductVariant $variant, int $quantity): void
    {
        if ($variant->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient inventory for this variant.'],
            ]);
        }

        $variant->stock_quantity -= $quantity;
        $variant->save();
    }

    private function decrementSharedStock(Product $product, ProductVariant $selectedVariant, int $quantity): void
    {
        /** @var Collection<int, ProductVariant> $sharedVariants */
        $sharedVariants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('inventory_strategy', 'shared')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $totalShared = $sharedVariants->sum('stock_quantity');
        if ($totalShared < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient shared inventory for this product.'],
            ]);
        }

        $ordered = $sharedVariants
            ->sortBy(fn (ProductVariant $item) => $item->id === $selectedVariant->id ? 0 : 1)
            ->values();

        $remaining = $quantity;
        foreach ($ordered as $item) {
            if ($remaining <= 0) {
                break;
            }

            $used = min($item->stock_quantity, $remaining);
            $item->stock_quantity -= $used;
            $item->save();
            $remaining -= $used;
        }
    }
}
