<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->numerify('####'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['asset','liability','equity','revenue','expense']),
        ];
    }
}