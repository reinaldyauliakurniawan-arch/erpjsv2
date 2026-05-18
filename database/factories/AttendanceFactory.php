<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_session_id' => \App\Models\ClassSession::factory(),
            'date' => fake()->date(),
            'time_block' => fake()->randomElement(['morning','afternoon','evening']),
            'classroom_id' => \App\Models\Classroom::factory(),
            'marked_by' => null,
            'status' => fake()->randomElement(['open','closed']),
            'notes' => fake()->sentence(),
        ];
    }
}