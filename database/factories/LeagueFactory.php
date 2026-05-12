<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeagueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' League',
            'country' => fake()->randomElement(['Brasil', 'Inglaterra', 'Espanha']),
            'season' => '2026',
            'is_active' => true,
        ];
    }
}
