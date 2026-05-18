<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JournalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'description' => fake()->sentence(),
            'reference' => fake()->bothify('JRN-####'),
            'total_amount' => fake()->numberBetween(100000, 5000000),
            'approved_by' => null,
        ];
    }
}