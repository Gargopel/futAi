<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisResult extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisResultFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'summary',
        'confidence',
        'risk_level',
        'suggested_market',
        'suggested_selection',
        'result_status',
        'data',
        'generated_at',
        'evaluated_at',
        'evaluation_reason',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'data' => 'array',
        'generated_at' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
