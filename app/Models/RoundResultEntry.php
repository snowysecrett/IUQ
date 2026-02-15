<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundResultEntry extends Model
{
    protected $fillable = [
        'round_result_id',
        'slot',
        'team_id',
        'display_name_snapshot',
        'score',
        'rank',
    ];

    public function roundResult(): BelongsTo
    {
        return $this->belongsTo(RoundResult::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
