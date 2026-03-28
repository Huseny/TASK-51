<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RideOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_id',
        'driver_id',
        'origin_address',
        'destination_address',
        'rider_count',
        'time_window_start',
        'time_window_end',
        'notes',
        'status',
        'accepted_at',
        'started_at',
        'completed_at',
        'canceled_at',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_window_start' => 'datetime',
            'time_window_end' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(RideOrderAuditLog::class);
    }
}
