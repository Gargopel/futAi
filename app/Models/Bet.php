<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    protected $fillable = [
        'bankroll_id',
        'bookmaker_id',
        'match_id',
        'placed_at',
        'market',
        'selection',
        'stake',
        'odds',
        'status',
        'payout',
        'settled_at',
        'notes',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'settled_at' => 'datetime',
        'stake' => 'decimal:2',
        'odds' => 'decimal:2',
        'payout' => 'decimal:2',
    ];

    public function bankroll(): BelongsTo
    {
        return $this->belongsTo(Bankroll::class);
    }

    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function profit(): float
    {
        $stake = (float) $this->stake;
        $odds = (float) $this->odds;

        return match ($this->status) {
            'won' => round(($stake * $odds) - $stake, 2),
            'lost' => -$stake,
            'void' => 0.0,
            'cashed_out' => round(((float) $this->payout) - $stake, 2),
            default => 0.0,
        };
    }
}
