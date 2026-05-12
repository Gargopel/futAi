<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballMatch extends Model
{
    /** @use HasFactory<\Database\Factories\FootballMatchFactory> */
    use HasFactory;

    protected $table = 'football_matches';

    protected $fillable = [
        'league_id',
        'home_team_id',
        'away_team_id',
        'starts_at',
        'status',
        'home_goals',
        'away_goals',
        'notes',
    ];

    protected $casts = ['starts_at' => 'datetime'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function teamMatchStats(): HasMany
    {
        return $this->hasMany(TeamMatchStat::class, 'match_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }

    public function analysisResults(): HasMany
    {
        return $this->hasMany(AnalysisResult::class, 'match_id');
    }
}
