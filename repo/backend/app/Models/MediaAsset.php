<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'original_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256_hash',
        'disk_path',
        'compressed_path',
        'uploaded_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_media')
            ->withPivot(['id', 'sort_order', 'is_cover', 'created_at']);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png'], true);
    }

    public function isVideo(): bool
    {
        return $this->mime_type === 'video/mp4';
    }
}
