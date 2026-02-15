<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundAction extends Model
{
    protected $fillable = [
        'round_id',
        'user_id',
        'action_type',
        'payload',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
