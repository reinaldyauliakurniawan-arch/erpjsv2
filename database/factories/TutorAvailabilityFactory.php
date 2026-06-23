<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TutorAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id'   => \App\Models\Tutor::factory(),
            'day'        => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']),
            'time_block' => fake()->randomElement(['08:00-09:30', '09:30-11:00', '13:00-14:30', '15:00-16:30']),
            // Bug fix: enum values must match the migration.
            // Migration allows: available, not_available, occupied.
            // Previously used 'unavailable' which would fail enum constraint.
            'status'     => 'available',
        ];
    }

    /**
     * Mark availability as occupied (used when an enrollment schedules this slot).
     */
    public function occupied(): static
    {
        return $this->state(fn () => ['status' => 'occupied']);
    }

    /**
     * Mark availability as not_available (tutor explicitly blocked this slot).
     */
    public function notAvailable(): static
    {
        return $this->state(fn () => ['status' => 'not_available']);
    }
}
