<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancementLog extends Model
{
    protected $fillable = [
        'tournament_id',
        'rule_id',
        'user_id',
        'source_type',
        'source_round_id',
        'source_group_id',
        'target_round_id',
        'target_slot',
        'team_id_before',
        'team_id_after',
        'status',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AdvancementRule::class, 'rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function beforeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id_before');
    }

    public function afterTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id_after');
    }
}
