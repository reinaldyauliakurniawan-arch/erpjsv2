<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['private','group']),
            'price' => fake()->numberBetween(500000, 5000000),
            'total_meetings' => fake()->numberBetween(8, 24),
            'min_quota' => fake()->numberBetween(2, 5),
        ];
    }
}