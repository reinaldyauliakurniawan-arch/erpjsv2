<?php

namespace Tests\Unit\Services;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\BalanceMismatchException;
use App\Exceptions\IdempotencyException;
use App\Models\Account;
use App\Models\Journal;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountingService();

        // Seed akun-akun yang dibutuhkan
        Account::factory()->create(['code' => '1101', 'name' => 'Cash/Bank']);
        Account::factory()->create(['code' => '2201', 'name' => 'Deferred Revenue']);
        Account::factory()->create(['code' => '4101', 'name' => 'Tuition Fees Revenue']);
    }

    // =========================================================
    //  HAPPY PATH
    // =========================================================

    #[Test]
    public function it_creates_a_balanced_journal_entry()
    {
        $journal = $this->service->createJournal(
            date: '2025-01-01',
            description: 'Test Journal',
            reference: 'TEST-001',
            items: [
                ['account_code' => '1101', 'debit' => 500_000, 'credit' => 0],
                ['account_code' => '2201', 'debit' => 0,       'credit' => 500_000],
            ]
        );

        $this->assertDatabaseHas('journals', [
            'reference'    => 'TEST-001',
            'total_amount' => 500_000,
        ]);

        $this->assertDatabaseHas('journal_items', ['debit' => 500_000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_items', ['debit' => 0,       'credit' => 500_000]);
        $this->assertInstanceOf(Journal::class, $journal);
    }

    #[Test]
    public function it_returns_the_created_journal_instance()
    {
        $journal = $this->service->createJournal(
            '2025-01-01', 'Return Test', 'REF-RETURN',
            [
                ['account_code' => '1101', 'debit' => 100_000, 'credit' => 0],
                ['account_code' => '2201', 'debit' => 0,       'credit' => 100_000],
            ]
        );

        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertEquals('REF-RETURN', $journal->reference);
    }

    // =========================================================
    //  VALIDASI BALANCE
    // =========================================================

    #[Test]
    public function it_throws_balance_mismatch_when_debit_not_equal_credit()
    {
        $this->expectException(BalanceMismatchException::class);

        $this->service->createJournal(
            '2025-01-01', 'Unbalanced', 'UNBAL-001',
            [
                ['account_code' => '1101', 'debit' => 500_000, 'credit' => 0],
                ['account_code' => '2201', 'debit' => 0,       'credit' => 300_000], // sengaja beda
            ]
        );
    }

    #[Test]
    public function it_allows_tiny_floating_point_difference_within_tolerance()
    {
        // Selisih < 0.001 masih dianggap balance
        $journal = $this->service->createJournal(
            '2025-01-01', 'Float Tolerance', 'FLOAT-001',
            [
                ['account_code' => '1101', 'debit' => 100_000.0004, 'credit' => 0],
                ['account_code' => '2201', 'debit' => 0,            'credit' => 100_000],
            ]
        );

        $this->assertNotNull($journal);
    }

    // =========================================================
    //  IDEMPOTENCY
    // =========================================================

    #[Test]
    public function it_throws_idempotency_exception_on_duplicate_reference()
    {
        $items = [
            ['account_code' => '1101', 'debit' => 200_000, 'credit' => 0],
            ['account_code' => '2201', 'debit' => 0,       'credit' => 200_000],
        ];

        $this->service->createJournal('2025-01-01', 'First', 'IDEM-001', $items);

        $this->expectException(IdempotencyException::class);

        $this->service->createJournal('2025-01-02', 'Second', 'IDEM-001', $items); // reference sama
    }

    #[Test]
    public function it_does_not_create_duplicate_journal_on_idempotency_failure()
    {
        $items = [
            ['account_code' => '1101', 'debit' => 200_000, 'credit' => 0],
            ['account_code' => '2201', 'debit' => 0,       'credit' => 200_000],
        ];

        $this->service->createJournal('2025-01-01', 'First', 'IDEM-NODUP', $items);

        try {
            $this->service->createJournal('2025-01-02', 'Second', 'IDEM-NODUP', $items);
        } catch (IdempotencyException) {}

        $this->assertEquals(1, Journal::where('reference', 'IDEM-NODUP')->count());
    }

    // =========================================================
    //  ACCOUNT NOT FOUND
    // =========================================================

    #[Test]
    public function it_throws_account_not_found_for_invalid_account_code()
    {
        $this->expectException(AccountNotFoundException::class);

        $this->service->createJournal(
            '2025-01-01', 'Bad Account', 'BAD-ACC-001',
            [
                ['account_code' => '9999', 'debit' => 100_000, 'credit' => 0], // tidak ada
                ['account_code' => '1101', 'debit' => 0,       'credit' => 100_000],
            ]
        );
    }

    #[Test]
    public function it_rolls_back_transaction_when_account_not_found()
    {
        try {
            $this->service->createJournal(
                '2025-01-01', 'Rollback Test', 'ROLLBACK-001',
                [
                    ['account_code' => '1101', 'debit' => 100_000, 'credit' => 0],
                    ['account_code' => '9999', 'debit' => 0,       'credit' => 100_000],
                ]
            );
        } catch (AccountNotFoundException) {}

        $this->assertDatabaseMissing('journals', ['reference' => 'ROLLBACK-001']);
    }
}
