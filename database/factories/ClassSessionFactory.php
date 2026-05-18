<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'program_id' => \App\Models\Program::factory(),
            'status' => fake()->randomElement(['open','closed']),
        ];
    }
}