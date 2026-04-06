<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationFeatureValue extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'feature_set_id',
        'user_id',
        'item_id',
        'feature_key',
        'feature_value',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature_value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function featureSet(): BelongsTo
    {
        return $this->belongsTo(RecommendationFeatureSet::class, 'feature_set_id');
    }
}
