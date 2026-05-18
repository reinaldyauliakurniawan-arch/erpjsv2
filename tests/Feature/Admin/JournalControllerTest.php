<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JournalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);

        Account::factory()->create(['code' => '1101', 'name' => 'Cash/Bank']);
        Account::factory()->create(['code' => '2201', 'name' => 'Deferred Revenue']);
    }

    // =========================================================
    //  INDEX & CREATE
    // =========================================================

    #[Test]
    public function admin_can_view_journal_list()
    {
        $this->actingAs($this->admin)
            ->get(route('finance.journals.index'))
            ->assertOk()
            ->assertViewIs('admin.journals.index');
    }

    #[Test]
    public function admin_can_view_journal_create_form()
    {
        $this->actingAs($this->admin)
            ->get(route('finance.journals.create'))
            ->assertOk();
    }

    // =========================================================
    //  STORE
    // =========================================================

    #[Test]
    public function admin_can_create_manual_journal_entry()
    {
        $this->actingAs($this->admin)
            ->post(route('finance.journals.store'), [
                'date'        => '2025-01-15',
                'description' => 'Manual adjustment',
                'reference'   => 'MAN-001',
                'items'       => [
                    ['account_code' => '1101', 'debit' => 100_000, 'credit' => 0],
                    ['account_code' => '2201', 'debit' => 0,       'credit' => 100_000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('journals', ['reference' => 'MAN-001']);
    }

    #[Test]
    public function store_journal_fails_when_debit_not_equal_credit()
    {
        $this->actingAs($this->admin)
            ->post(route('finance.journals.store'), [
                'date'        => '2025-01-15',
                'description' => 'Unbalanced',
                'reference'   => 'UNBAL-001',
                'items'       => [
                    ['account_code' => '1101', 'debit' => 100_000, 'credit' => 0],
                    ['account_code' => '2201', 'debit' => 0,       'credit' => 50_000],
                ],
            ])
            ->assertSessionHasErrors();
    }

    // =========================================================
    //  SHOW
    // =========================================================

    #[Test]
    public function admin_can_view_journal_detail()
    {
        $journal = Journal::factory()->withItems()->create();

        $this->actingAs($this->admin)
            ->get(route('finance.journals.show', $journal))
            ->assertOk()
            ->assertViewIs('admin.journals.show');
    }

    // =========================================================
    //  REVERSE
    // =========================================================

    #[Test]
    public function admin_can_reverse_a_journal()
    {
        $journal = Journal::factory()->withItems()->create(['reference' => 'ORIG-001']);

        $this->actingAs($this->admin)
            ->post(route('finance.journals.reverse', $journal))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Reversal journal harus ada dengan reference yang mencerminkan reversal
        $this->assertDatabaseHas('journals', ['reference' => 'REV-ORIG-001']);
    }

    #[Test]
    public function reversing_same_journal_twice_is_prevented()
    {
        $journal = Journal::factory()->withItems()->create(['reference' => 'ORIG-DUP']);

        $this->actingAs($this->admin)
            ->post(route('finance.journals.reverse', $journal));

        // Coba reverse lagi
        $this->actingAs($this->admin)
            ->post(route('finance.journals.reverse', $journal))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function guest_cannot_access_journals()
    {
        $this->get(route('finance.journals.index'))
            ->assertRedirect(route('login'));
    }
}
