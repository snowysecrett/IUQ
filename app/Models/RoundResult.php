<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoundResult extends Model
{
    protected $fillable = [
        'round_id',
        'finalized_by_user_id',
        'finalized_at',
        'is_overridden',
        'is_stale',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'is_overridden' => 'boolean',
            'is_stale' => 'boolean',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RoundResultEntry::class)->orderBy('rank')->orderBy('slot');
    }
}
