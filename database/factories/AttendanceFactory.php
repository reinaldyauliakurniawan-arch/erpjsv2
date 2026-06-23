<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_session_id' => \App\Models\ClassSession::factory(),
            'date'             => fake()->date(),
            'time_block'       => fake()->randomElement(['morning', 'afternoon', 'evening']),
            'classroom_id'     => \App\Models\Classroom::factory(),
            // Bug fix: marked_by is NOT NULL in the schema.
            // Previously set to null which would fail SQL constraint.
            'marked_by'        => \App\Models\User::factory(),
            // Bug fix: status enum values must match the migration.
            // Migration allows: scheduled, ongoing, finished, skipped, postponed.
            // Previously used 'open'/'closed' which would fail enum constraint.
            'status'           => fake()->randomElement(['scheduled', 'ongoing', 'finished', 'skipped', 'postponed']),
            'notes'            => fake()->sentence(),
        ];
    }

    /**
     * Mark attendance as scheduled (default state for new sessions).
     */
    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => 'scheduled']);
    }

    /**
     * Mark attendance as finished (used for revenue recognition tests).
     */
    public function finished(): static
    {
        return $this->state(fn () => ['status' => 'finished']);
    }
}
