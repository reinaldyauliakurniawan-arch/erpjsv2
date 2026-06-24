<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FixedAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                => fake()->words(2, true),
            'category'            => fake()->randomElement(['Peralatan', 'Kendaraan', 'Bangunan', 'Furnitur']),
            'acquired_at'         => fake()->date(),
            'cost'                => fake()->numberBetween(1_000_000, 50_000_000),
            'salvage_value'       => fake()->numberBetween(0, 1_000_000),
            'useful_life'         => fake()->numberBetween(12, 60),
            'depreciation_method' => 'straight_line',
            'notes'               => null,
            'is_active'           => true,
        ];
    }
}
