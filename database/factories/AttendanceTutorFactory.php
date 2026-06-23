<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceTutorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'tutor_id'      => \App\Models\Tutor::factory(),
            'payable_amount' => fake()->numberBetween(50000, 500000),
            // Bug fix: pending_rate must be boolean false by default.
            // PayrollService approves only attendances where pending_rate = false.
            // Previously this was set to a random int, breaking all payroll tests.
            'pending_rate'  => false,
            'journal_id'    => null,
            'paid_at'       => null,
            'is_replacement' => false,
            'replaced_tutor_id' => null,
            'is_team_teaching' => false,
        ];
    }

    /**
     * Mark this attendance-tutor row as having a pending rate (excluded from payroll).
     */
    public function pendingRate(): static
    {
        return $this->state(fn () => ['pending_rate' => true]);
    }

    /**
     * Mark this attendance-tutor row as already paid (excluded from payroll).
     */
    public function paid(): static
    {
        return $this->state(fn () => ['paid_at' => now()]);
    }
}
