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
            // Sesuai konvensi data asli (ChartOfAccountsSeeder + validasi
            // AccountController): tipe akun kapital, bukan huruf kecil.
            'type' => fake()->randomElement(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']),
        ];
    }
}
