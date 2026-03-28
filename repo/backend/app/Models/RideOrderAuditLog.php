<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideOrderAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ride_order_id',
        'from_status',
        'to_status',
        'triggered_by',
        'trigger_reason',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function rideOrder(): BelongsTo
    {
        return $this->belongsTo(RideOrder::class);
    }
}
