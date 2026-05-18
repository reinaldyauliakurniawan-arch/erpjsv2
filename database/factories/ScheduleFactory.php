<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => \App\Models\Enrollment::factory(),
            'classroom_id' => \App\Models\Classroom::factory(),
            'day' => fake()->randomElement(['monday','tuesday','wednesday','thursday','friday','saturday']),
            'time_block' => fake()->randomElement(['morning','afternoon','evening']),
        ];
    }
}