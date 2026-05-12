<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bankroll extends Model
{
    protected $fillable = ['bookmaker_id', 'name', 'initial_balance', 'currency', 'is_active', 'notes'];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class);
    }
}
