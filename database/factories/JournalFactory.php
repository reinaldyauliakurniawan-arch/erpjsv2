<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JournalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date'         => fake()->date(),
            'description'  => fake()->sentence(),
            'reference'    => fake()->unique()->bothify('JRN-####'),
            'total_amount' => fake()->numberBetween(100000, 5000000),
            'type'         => 'general',
            'approved_by'  => null,
        ];
    }

    /**
     * Create a journal with attached items (one debit + one credit of equal amount).
     */
    public function withItems(int $amount = 100_000): static
    {
        return $this->afterCreating(function (\App\Models\Journal $journal) use ($amount) {
            // Ensure accounts exist (defensive — tests usually seed them already)
            $cash = \App\Models\Account::firstOrCreate(
                ['code' => '1001'],
                ['name' => 'Cash', 'type' => 'Asset']
            );
            $deferred = \App\Models\Account::firstOrCreate(
                ['code' => '2002'],
                ['name' => 'Deferred Revenue', 'type' => 'Liability']
            );

            \App\Models\JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $cash->id,
                'debit'      => $amount,
                'credit'     => 0,
            ]);
            \App\Models\JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $deferred->id,
                'debit'      => 0,
                'credit'     => $amount,
            ]);
        });
    }
}
