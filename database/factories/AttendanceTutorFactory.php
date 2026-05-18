<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceTutorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'tutor_id' => \App\Models\Tutor::factory(),
            'payable_amount' => fake()->numberBetween(50000, 500000),
            'pending_rate' => fake()->numberBetween(50000, 200000),
            'journal_id' => null,
            'paid_at' => null,
        ];
    }
}