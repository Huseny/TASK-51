<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'label',
        'inventory_strategy',
        'stock_quantity',
        'presale_available_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presale_available_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PurchaseRecord::class, 'variant_id');
    }
}
