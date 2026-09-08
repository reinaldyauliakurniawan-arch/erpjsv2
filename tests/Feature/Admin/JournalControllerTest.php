<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountCode;
use App\Models\Account;
use App\Models\Journal;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JournalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();

        // Buku besar & jurnal manual ada di area /finance — hanya untuk CFO.
        $this->cfo = User::factory()->create(['role' => 'cfo']);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function accountId(AccountCode $code): int
    {
        return Account::where('code', $code->value)->value('id');
    }

    // =========================================================
    //  INDEX & CREATE
    // =========================================================

    #[Test]
    public function cfo_can_view_journal_list()
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.journals.index'))
            ->assertOk()
            ->assertViewIs('admin.journals.index');
    }

    #[Test]
    public function cfo_can_view_journal_create_form()
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.journals.create'))
            ->assertOk();
    }

    // =========================================================
    //  STORE
    // =========================================================

    #[Test]
    public function cfo_can_create_manual_journal_entry()
    {
        $this->actingAs($this->cfo)
            ->post(route('finance.journals.store'), [
                'date' => '2025-01-15',
                'description' => 'Manual adjustment',
                'reference' => 'MAN-001',
                'items' => [
                    ['account_id' => $this->accountId(AccountCode::CASH), 'debit' => 100_000, 'credit' => 0],
                    ['account_id' => $this->accountId(AccountCode::DEFERRED_REVENUE), 'debit' => 0, 'credit' => 100_000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('journals', ['reference' => 'MAN-001']);
    }

    #[Test]
    public function store_journal_fails_when_debit_not_equal_credit()
    {
        $this->actingAs($this->cfo)
            ->post(route('finance.journals.store'), [
                'date' => '2025-01-15',
                'description' => 'Unbalanced',
                'reference' => 'UNBAL-001',
                'items' => [
                    ['account_id' => $this->accountId(AccountCode::CASH), 'debit' => 100_000, 'credit' => 0],
                    ['account_id' => $this->accountId(AccountCode::DEFERRED_REVENUE), 'debit' => 0, 'credit' => 50_000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('journals', ['reference' => 'UNBAL-001']);
    }

    // =========================================================
    //  SHOW
    // =========================================================

    #[Test]
    public function cfo_can_view_journal_detail()
    {
        $journal = Journal::factory()->withItems()->create();

        $this->actingAs($this->cfo)
            ->get(route('finance.journals.show', $journal))
            ->assertOk()
            ->assertViewIs('admin.journals.show');
    }

    // =========================================================
    //  REVERSE
    // =========================================================

    #[Test]
    public function cfo_can_reverse_a_journal()
    {
        $journal = Journal::factory()->withItems()->create(['reference' => 'ORIG-001']);

        $this->actingAs($this->cfo)
            ->post(route('finance.journals.reverse', $journal))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Jurnal balik harus ada dengan reference REV-<asli>
        $this->assertDatabaseHas('journals', ['reference' => 'REV-ORIG-001']);
    }

    #[Test]
    public function reversing_same_journal_twice_is_prevented()
    {
        $journal = Journal::factory()->withItems()->create(['reference' => 'ORIG-DUP']);

        $this->actingAs($this->cfo)
            ->post(route('finance.journals.reverse', $journal));

        // Coba reverse lagi
        $this->actingAs($this->cfo)
            ->post(route('finance.journals.reverse', $journal))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    #[Test]
    public function guest_cannot_access_journals()
    {
        $this->get(route('finance.journals.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_cannot_access_finance_journals()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('finance.journals.index'))
            ->assertForbidden();
    }
}
