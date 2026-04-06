<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationResult extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'model_version_id',
        'feature_set_id',
        'user_id',
        'item_id',
        'score',
        'rank_order',
        'is_exploration',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'is_exploration' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(RecommendationModel::class, 'model_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function featureSet(): BelongsTo
    {
        return $this->belongsTo(RecommendationFeatureSet::class, 'feature_set_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'item_id');
    }
}
