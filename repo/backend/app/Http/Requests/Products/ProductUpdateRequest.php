<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'purchase_limit' => ['nullable', 'integer', 'min:0'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.label' => ['required', 'string', 'max:100'],
            'variants.*.inventory_strategy' => ['required', 'in:presale,live_stock,shared'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.presale_available_date' => ['nullable', 'date'],
            'variants.*.tiers' => ['required', 'array', 'min:1'],
            'variants.*.tiers.*.min_quantity' => ['required', 'integer', 'min:1'],
            'variants.*.tiers.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'variants.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Product|null $product */
            $product = $this->route('product');
            $variants = $this->input('variants', []);
            $productVariantIds = $product
                ? $product->variants()->pluck('id')->all()
                : [];

            foreach ($variants as $variantIndex => $variant) {
                $variantId = $variant['id'] ?? null;

                if ($variantId !== null && ! in_array((int) $variantId, $productVariantIds, true)) {
                    $validator->errors()->add("variants.$variantIndex.id", 'Variant does not belong to this product.');
                }

                $sku = (string) ($variant['sku'] ?? '');
                if ($sku !== '') {
                    $skuConflict = ProductVariant::query()
                        ->where('sku', $sku)
                        ->when($variantId !== null, fn ($query) => $query->where('id', '!=', (int) $variantId))
                        ->exists();

                    if ($skuConflict) {
                        $validator->errors()->add("variants.$variantIndex.sku", 'The sku has already been taken.');
                    }
                }

                $tiers = $variant['tiers'] ?? [];
                usort($tiers, fn (array $a, array $b) => ($a['min_quantity'] ?? 0) <=> ($b['min_quantity'] ?? 0));

                $lastMax = 0;

                foreach ($tiers as $tierIndex => $tier) {
                    $min = (int) ($tier['min_quantity'] ?? 0);
                    $max = $tier['max_quantity'] ?? null;

                    if ($max !== null && (int) $max < $min) {
                        $validator->errors()->add("variants.$variantIndex.tiers.$tierIndex.max_quantity", 'max_quantity must be greater than or equal to min_quantity.');
                    }

                    if ($min <= $lastMax) {
                        $validator->errors()->add("variants.$variantIndex.tiers.$tierIndex.min_quantity", 'Pricing tiers must not overlap.');
                    }

                    $lastMax = $max === null ? PHP_INT_MAX : (int) $max;
                }

                if (($variant['inventory_strategy'] ?? null) === 'presale' && empty($variant['presale_available_date'])) {
                    $validator->errors()->add("variants.$variantIndex.presale_available_date", 'presale_available_date is required for presale inventory strategy.');
                }
            }
        });
    }
}
