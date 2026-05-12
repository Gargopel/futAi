<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $fillable = ['league_id', 'name', 'short_name', 'country', 'logo_url', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_team_id');
    }

    public function matchStats(): HasMany
    {
        return $this->hasMany(TeamMatchStat::class);
    }
}
