<?php

namespace Database\Factories;

use App\Support\ScheduleFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => \App\Models\Enrollment::factory(),
            'classroom_id' => \App\Models\Classroom::factory(),
            // Format baku: hari Bahasa Indonesia, jam pakai titik dua.
            'day' => fake()->randomElement(ScheduleFormat::DAYS),
            'time_block' => fake()->randomElement(ScheduleFormat::TIME_BLOCKS),
        ];
    }
}
