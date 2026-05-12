<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class FootballMatchFactory extends Factory
{
    public function definition(): array
    {
        $league = League::factory()->create();
        $homeTeam = Team::factory()->create(['league_id' => $league->id]);
        $awayTeam = Team::factory()->create(['league_id' => $league->id]);

        return [
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'starts_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'status' => 'scheduled',
            'home_goals' => null,
            'away_goals' => null,
            'notes' => null,
        ];
    }
}
