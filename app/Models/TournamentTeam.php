<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TournamentTeam extends Pivot
{
    protected $table = 'tournament_teams';

    protected $fillable = [
        'tournament_id',
        'team_id',
        'display_name_snapshot',
        'icon_snapshot_path',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
