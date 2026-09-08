<?php

namespace Database\Factories;

use App\Support\ScheduleFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

class TutorAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id'   => \App\Models\Tutor::factory(),
            // Format baku: hari Bahasa Indonesia, jam pakai titik dua.
            'day'        => fake()->randomElement(ScheduleFormat::DAYS),
            'time_block' => fake()->randomElement(ScheduleFormat::TIME_BLOCKS),
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
