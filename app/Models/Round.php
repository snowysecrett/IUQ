<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Round extends Model
{
    protected $fillable = [
        'tournament_id',
        'round_template_id',
        'group_id',
        'name',
        'code',
        'teams_per_round',
        'default_score',
        'status',
        'phase',
        'has_fever',
        'has_ultimate_fever',
        'scheduled_start_at',
        'score_deltas',
        'lightning_score_deltas',
        'buzzer_normal_score_deltas',
        'buzzer_fever_score_deltas',
        'buzzer_ultimate_score_deltas',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'score_deltas' => 'array',
            'lightning_score_deltas' => 'array',
            'buzzer_normal_score_deltas' => 'array',
            'buzzer_fever_score_deltas' => 'array',
            'buzzer_ultimate_score_deltas' => 'array',
            'default_score' => 'integer',
            'has_fever' => 'boolean',
            'has_ultimate_fever' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoundTemplate::class, 'round_template_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RoundParticipant::class)->orderBy('slot');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(RoundScore::class)->orderBy('slot');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RoundAction::class)->latest('id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(RoundResult::class);
    }
}
