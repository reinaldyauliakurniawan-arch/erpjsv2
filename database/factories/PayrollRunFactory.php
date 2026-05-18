<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'month' => fake()->date('Y-m'),
            'status' => fake()->randomElement(['draft','approved']),
            'approved_by' => null,
        ];
    }
}