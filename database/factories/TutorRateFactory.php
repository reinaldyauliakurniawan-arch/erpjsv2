<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TutorRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id' => \App\Models\Tutor::factory(),
            'program_id' => \App\Models\Program::factory(),
            'rate' => fake()->numberBetween(50000, 200000),
        ];
    }
}