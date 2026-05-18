<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TutorAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id' => \App\Models\Tutor::factory(),
            'day' => fake()->randomElement(['monday','tuesday','wednesday','thursday','friday','saturday']),
            'time_block' => fake()->randomElement(['morning','afternoon','evening']),
            'status' => fake()->randomElement(['available','unavailable']),
        ];
    }
}