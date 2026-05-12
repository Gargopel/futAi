<?php

namespace Database\Factories;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => FootballMatch::factory(),
            'summary' => 'Analise inicial baseada em dados de exemplo.',
            'confidence' => fake()->randomFloat(2, 50, 85),
            'risk_level' => 'medium',
            'suggested_market' => 'Over 1.5 Goals',
            'suggested_selection' => 'Over 1.5',
            'data' => ['factors' => ['Base estatistica ainda em construcao']],
            'generated_at' => now(),
        ];
    }
}
