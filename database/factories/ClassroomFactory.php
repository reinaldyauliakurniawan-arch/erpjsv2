<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->bothify('Room ##'),
            'capacity' => fake()->numberBetween(5, 30),
        ];
    }
}