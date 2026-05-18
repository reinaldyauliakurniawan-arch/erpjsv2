<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => \App\Models\Enrollment::factory(),
            'amount' => fake()->numberBetween(500000, 2000000),
            'due_date' => fake()->date(),
            'paid_at' => null,
        ];
    }
}