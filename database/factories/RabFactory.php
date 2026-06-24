<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RabFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year'         => fake()->numberBetween(2024, 2026),
            'division'     => fake()->randomElement(\App\Models\Rab::divisions()),
            'account_name' => fake()->words(2, true),
            'activity'     => fake()->optional()->sentence(3),
            'q1'           => fake()->numberBetween(0, 5_000_000),
            'q2'           => fake()->numberBetween(0, 5_000_000),
            'q3'           => fake()->numberBetween(0, 5_000_000),
            'q4'           => fake()->numberBetween(0, 5_000_000),
        ];
    }
}
