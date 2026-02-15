<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'year',
        'status',
        'scheduled_start_at',
        'timezone',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'tournament_teams')
            ->using(TournamentTeam::class)
            ->withPivot(['display_name_snapshot', 'icon_snapshot_path'])
            ->withTimestamps();
    }

    public function tournamentTeams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function roundTemplates(): HasMany
    {
        return $this->hasMany(RoundTemplate::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function advancementRules(): HasMany
    {
        return $this->hasMany(AdvancementRule::class)->orderBy('source_type')->orderBy('priority')->orderBy('id');
    }

    public function advancementLogs(): HasMany
    {
        return $this->hasMany(AdvancementLog::class)->latest('id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');
    }

    public static function syncScheduledStatuses(): void
    {
        $now = now();

        static::query()
            ->where('status', 'draft')
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<=', $now)
            ->update(['status' => 'live']);

        $latestLive = static::query()
            ->where('status', 'live')
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('id')
            ->first();

        if ($latestLive) {
            static::query()
                ->where('status', 'live')
                ->where('id', '!=', $latestLive->id)
                ->update(['status' => 'completed']);
        }
    }
}
