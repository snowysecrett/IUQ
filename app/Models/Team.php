<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $appends = ['icon_url'];

    protected $fillable = [
        'university_name',
        'team_name',
        'short_name',
        'icon_path',
    ];

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_teams')
            ->using(TournamentTeam::class)
            ->withTimestamps();
    }

    public function tournamentTeams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function getIconUrlAttribute(): ?string
    {
        return MediaPath::toUrl($this->icon_path);
    }
}
