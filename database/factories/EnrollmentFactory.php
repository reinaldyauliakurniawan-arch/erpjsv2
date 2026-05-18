<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'program_id' => \App\Models\Program::factory(),
            'class_session_id' => null,
            'payment_method' => fake()->randomElement(['full', 'installment']),
            'payment_status' => fake()->randomElement(['pending', 'partial', 'full']),
            'status' => fake()->randomElement(['active', 'waitlist', 'expired', 'graduate']),
            'total_amount' => fake()->numberBetween(500000, 5000000),
            'remaining_meetings' => fake()->numberBetween(0, 24),
            'enrollment_date' => fake()->date(),
            'expiry_date' => fake()->date(),
        ];
    }
}
