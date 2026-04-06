<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecommendationModel extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'version',
        'is_active',
        'feature_snapshot',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'feature_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(RecommendationResult::class, 'model_version_id');
    }

    public function featureSet(): HasOne
    {
        return $this->hasOne(RecommendationFeatureSet::class, 'recommendation_model_id');
    }
}
