<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundParticipant extends Model
{
    protected $fillable = [
        'round_id',
        'slot',
        'team_id',
        'display_name_snapshot',
        'icon_snapshot_path',
        'assignment_mode',
        'assignment_source_type',
        'assignment_source_id',
        'assignment_source_rank',
        'assignment_reason',
        'assignment_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'assignment_updated_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
