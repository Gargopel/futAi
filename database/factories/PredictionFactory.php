<?php

namespace Database\Factories;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class PredictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => FootballMatch::factory(),
            'market' => 'Over 1.5 Goals',
            'selection' => 'Over 1.5',
            'confidence' => fake()->randomFloat(2, 50, 90),
            'risk_level' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'pending',
            'reasoning' => fake()->sentence(),
        ];
    }
}
