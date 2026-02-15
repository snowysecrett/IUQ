<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancementRule extends Model
{
    protected $fillable = [
        'tournament_id',
        'source_type',
        'source_round_id',
        'source_group_id',
        'source_rank',
        'action_type',
        'target_round_id',
        'target_slot',
        'is_active',
        'priority',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceRound(): BelongsTo
    {
        return $this->belongsTo(Round::class, 'source_round_id');
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'source_group_id');
    }

    public function targetRound(): BelongsTo
    {
        return $this->belongsTo(Round::class, 'target_round_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
