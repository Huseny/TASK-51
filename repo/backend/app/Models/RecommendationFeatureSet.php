<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationFeatureSet extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recommendation_model_id',
        'version',
        'schema_version',
        'seed',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'schema_version' => 'integer',
            'seed' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(RecommendationModel::class, 'recommendation_model_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(RecommendationFeatureValue::class, 'feature_set_id');
    }
}
