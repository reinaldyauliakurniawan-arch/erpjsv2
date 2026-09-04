<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'program_id' => Program::factory(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
