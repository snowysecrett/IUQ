<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoundTemplate extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'code',
        'teams_per_round',
        'default_score',
        'default_score_deltas',
        'has_fever',
        'has_ultimate_fever',
        'default_lightning_score_deltas',
        'default_buzzer_normal_score_deltas',
        'default_buzzer_fever_score_deltas',
        'default_buzzer_ultimate_score_deltas',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_score' => 'integer',
            'default_score_deltas' => 'array',
            'default_lightning_score_deltas' => 'array',
            'default_buzzer_normal_score_deltas' => 'array',
            'default_buzzer_fever_score_deltas' => 'array',
            'default_buzzer_ultimate_score_deltas' => 'array',
            'has_fever' => 'boolean',
            'has_ultimate_fever' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
