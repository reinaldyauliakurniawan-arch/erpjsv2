<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'program_id' => Program::factory(),
            'class_session_id' => null,
            'payment_method' => fake()->randomElement(['full upfront', 'installment']),
            'payment_status' => fake()->randomElement(['pending', 'partial', 'full']),
            'status' => fake()->randomElement(['active', 'waitlist', 'expired', 'graduate']),
            'total_amount' => fake()->numberBetween(500000, 5000000),
            'remaining_meetings' => fake()->numberBetween(0, 24),
            'enrollment_date' => fake()->date(),
            'expiry_date' => fake()->date(),
        ];
    }

    /** Enrollment aktif dengan class session yang programnya konsisten. */
    public function withRelations(): static
    {
        return $this->afterMaking(function ($enrollment) {
            if (! $enrollment->class_session_id) {
                $enrollment->class_session_id = ClassSession::factory()
                    ->create(['program_id' => $enrollment->program_id, 'status' => 'active'])->id;
            }
        });
    }
}
