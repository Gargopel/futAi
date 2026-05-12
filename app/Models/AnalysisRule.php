<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisRule extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisRuleFactory> */
    use HasFactory;

    protected $fillable = ['name', 'key', 'description', 'weight', 'is_active', 'config'];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'config' => 'array',
    ];
}
