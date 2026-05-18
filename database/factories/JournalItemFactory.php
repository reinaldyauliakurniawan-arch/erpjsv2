<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JournalItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'journal_id' => \App\Models\Journal::factory(),
            'account_id' => \App\Models\Account::factory(),
            'debit' => fake()->numberBetween(0, 5000000),
            'credit' => fake()->numberBetween(0, 5000000),
        ];
    }
}