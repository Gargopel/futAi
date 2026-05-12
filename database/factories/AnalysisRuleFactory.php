<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisRuleFactory extends Factory
{
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'name' => fake()->words(3, true),
            'key' => $key,
            'description' => fake()->sentence(),
            'weight' => fake()->randomFloat(2, 0.5, 2),
            'is_active' => true,
            'config' => ['placeholder' => true],
        ];
    }
}
