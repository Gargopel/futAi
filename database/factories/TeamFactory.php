<?php

namespace Database\Factories;

use App\Models\League;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'league_id' => League::factory(),
            'name' => $name,
            'short_name' => substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $name)), 0, 3),
            'country' => 'Brasil',
            'logo_url' => null,
            'is_active' => true,
        ];
    }
}
