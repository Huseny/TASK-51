<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_identifier',
        'key',
        'request_path',
        'request_method',
        'canonical_path',
        'request_hash',
        'response_code',
        'response_body',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
