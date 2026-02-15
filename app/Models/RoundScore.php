<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundScore extends Model
{
    protected $fillable = [
        'round_id',
        'slot',
        'score',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
