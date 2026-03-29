<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class PricingService
{
    public function calculateTotal(ProductVariant $variant, int $quantity): float
    {
        $tier = $variant->pricingTiers()
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($quantity): void {
                $query->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderByDesc('min_quantity')
            ->first();

        if (! $tier) {
            throw ValidationException::withMessages([
                'quantity' => ['No pricing tier is configured for this quantity.'],
            ]);
        }

        return round((float) $tier->unit_price * $quantity, 2);
    }
}
