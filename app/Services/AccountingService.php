<?php

namespace App\Services;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\BalanceMismatchException;
use App\Exceptions\IdempotencyException;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create a journal entry.
     *
     * @param  array  $items  Array of ['account_code' => string, 'debit' => float, 'credit' => float]
     *
     * @throws BalanceMismatchException
     * @throws AccountNotFoundException
     * @throws IdempotencyException
     */
    public function createJournal(string $date, string $description, string $reference, array $items, string $type = 'general', ?int $programId = null, ?int $enrollmentId = null): Journal
    {
        // 1. Validate balance
        $totalDebit = collect($items)->sum('debit');
        $totalCredit = collect($items)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new BalanceMismatchException("Total debit ({$totalDebit}) does not equal total credit ({$totalCredit}).");
        }

        return DB::transaction(function () use ($date, $description, $reference, $items, $totalDebit, $type, $programId, $enrollmentId) {
            // 2. Idempotency check di dalam transaction untuk cegah race condition
            if (Journal::where('reference', $reference)->lockForUpdate()->exists()) {
                throw new IdempotencyException("Journal with reference {$reference} already exists.");
            }

            $journal = Journal::create([
                'date' => $date,
                'description' => $description,
                'reference' => $reference,
                'total_amount' => $totalDebit,
                'type' => $type,
                'enrollment_id' => $enrollmentId,
            ]);

            foreach ($items as $item) {
                $account = Account::where('code', $item['account_code'])->first();

                if (! $account) {
                    throw new AccountNotFoundException("Account with code {$item['account_code']} not found.");
                }

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $account->id,
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'program_id' => $programId,
                ]);
            }

            return $journal;
        });
    }
}
