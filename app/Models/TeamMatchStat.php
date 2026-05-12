<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMatchStat extends Model
{
    /** @use HasFactory<\Database\Factories\TeamMatchStatFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'team_id',
        'shots',
        'shots_on_target',
        'corners',
        'yellow_cards',
        'red_cards',
        'possession',
        'expected_goals',
    ];

    protected $casts = [
        'possession' => 'decimal:2',
        'expected_goals' => 'decimal:2',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
