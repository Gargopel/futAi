<?php

namespace Database\Factories;

use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamMatchStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => FootballMatch::factory(),
            'team_id' => Team::factory(),
            'shots' => fake()->numberBetween(3, 20),
            'shots_on_target' => fake()->numberBetween(1, 10),
            'corners' => fake()->numberBetween(0, 12),
            'yellow_cards' => fake()->numberBetween(0, 5),
            'red_cards' => fake()->numberBetween(0, 1),
            'possession' => fake()->randomFloat(2, 35, 65),
            'expected_goals' => fake()->randomFloat(2, 0, 3),
        ];
    }
}
