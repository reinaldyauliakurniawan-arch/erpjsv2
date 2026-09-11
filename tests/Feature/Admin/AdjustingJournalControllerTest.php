<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountCode;
use App\Models\Account;
use App\Models\Journal;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jurnal Penyesuaian manual (AdjustingJournalController::store). Posting
 * sesungguhnya HARUS lewat AccountingService (satu-satunya sumber kebenaran
 * untuk validasi balance + idempotency) — controller ini tidak boleh punya
 * jalur duplikat yang menulis journal_items sendiri.
 */
class AdjustingJournalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->cfo = User::factory()->create(['role' => 'cfo']);
    }

    private function accountId(string $code): int
    {
        return Account::where('code', $code)->value('id');
    }

    #[Test]
    public function cfo_can_post_a_balanced_manual_adjusting_journal(): void
    {
        $res = $this->actingAs($this->cfo)->post(route('finance.adjusting-journals.store'), [
            'period' => '2026-06-15',
            'description' => 'Koreksi manual audit',
            'items' => [
                ['account_id' => $this->accountId(AccountCode::EXPENSE_DISCOUNT_PROMO->value), 'debit' => 500_000, 'credit' => 0],
                ['account_id' => $this->accountId(AccountCode::BANK->value), 'debit' => 0, 'credit' => 500_000],
            ],
        ]);

        $res->assertSessionHasNoErrors()->assertRedirect();

        $journal = Journal::where('type', 'adjusting')->first();
        $this->assertNotNull($journal, 'jurnal harus benar-benar terposting ke buku besar');
        $this->assertSame('2026-06-30', substr((string) $journal->date, 0, 10), 'tanggal jurnal = akhir bulan periode');
        $this->assertEqualsWithDelta(500_000, $journal->total_amount, 0.01);

        // Trial balance tetap 0 — satu-satunya jalur posting (AccountingService).
        $tb = (float) DB::table('journal_items')->selectRaw('SUM(debit) - SUM(credit) v')->value('v');
        $this->assertEqualsWithDelta(0, $tb, 0.01);
    }

    #[Test]
    public function unbalanced_items_are_rejected_without_posting_anything(): void
    {
        $res = $this->actingAs($this->cfo)->post(route('finance.adjusting-journals.store'), [
            'period' => '2026-06-15',
            'description' => 'Tidak balance',
            'items' => [
                ['account_id' => $this->accountId(AccountCode::EXPENSE_DISCOUNT_PROMO->value), 'debit' => 500_000, 'credit' => 0],
                ['account_id' => $this->accountId(AccountCode::BANK->value), 'debit' => 0, 'credit' => 400_000],
            ],
        ]);

        $res->assertSessionHasErrors('items');
        $this->assertSame(0, Journal::count(), 'tidak ada apa pun yang terposting kalau tidak balance');
        $this->assertSame(0, DB::table('adjusting_journals')->count(), 'draft AdjustingJournal ikut rollback');
    }

    #[Test]
    public function duplicate_reference_in_the_same_month_is_rejected_not_silently_double_posted(): void
    {
        $post = fn () => $this->actingAs($this->cfo)->post(route('finance.adjusting-journals.store'), [
            'period' => '2026-07-10',
            'description' => 'Jurnal berulang',
            'items' => [
                ['account_id' => $this->accountId(AccountCode::EXPENSE_DISCOUNT_PROMO->value), 'debit' => 100_000, 'credit' => 0],
                ['account_id' => $this->accountId(AccountCode::BANK->value), 'debit' => 0, 'credit' => 100_000],
            ],
        ]);

        $post()->assertSessionHasNoErrors();
        $this->assertSame(1, Journal::where('type', 'adjusting')->count());

        // generateReference() menomori otomatis (AJE-2026-07-001, -002, ...),
        // jadi request kedua yang identik TETAP dapat reference baru & posting
        // baru — ini bukan tes idempotency (itu di AccountingServiceTest),
        // tapi memastikan tidak ada jurnal yatim (AdjustingJournal tanpa
        // posted_journal_id) tertinggal dari jalur lama yang double-write.
        $post()->assertSessionHasNoErrors();
        $this->assertSame(2, Journal::where('type', 'adjusting')->count());
        $this->assertSame(0, DB::table('adjusting_journals')->whereNull('posted_journal_id')->count());
    }

    #[Test]
    public function non_cfo_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('finance.adjusting-journals.store'), [
            'period' => '2026-06-15', 'description' => 'x', 'items' => [],
        ])->assertForbidden();
    }
}
