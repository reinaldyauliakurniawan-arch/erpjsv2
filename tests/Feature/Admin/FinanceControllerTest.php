<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cfo = User::factory()->cfo()->create();
        $this->cfo->role = 'cfo';
        $this->cfo->save();

        // Seed required accounts
        Account::factory()->create(['code' => '1001', 'name' => 'Cash', 'type' => 'Asset']);
        Account::factory()->create(['code' => '1002', 'name' => 'Bank', 'type' => 'Asset']);
        Account::factory()->create(['code' => '2002', 'name' => 'Deferred Revenue', 'type' => 'Liability']);
        Account::factory()->create(['code' => '4101', 'name' => 'Revenue', 'type' => 'Revenue']);
        Account::factory()->create(['code' => '5101', 'name' => 'Expense', 'type' => 'Expense']);
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('finance.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function cfo_can_view_finance_dashboard(): void
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertViewIs('admin.finance.dashboard');
    }

    #[Test]
    public function cfo_can_view_reports_page(): void
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.reports'))
            ->assertOk();
    }

    #[Test]
    public function cfo_can_get_revenue_chart_data(): void
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.chart.revenue-by-program'))
            ->assertOk()
            ->assertJsonStructure([]);
    }

    #[Test]
    public function dashboard_shows_cumulative_profit_loss_summary(): void
    {
        $acc = fn ($code) => Account::where('code', $code)->first()->id;
        // Pendapatan diakui total 500rb (200rb tahun lalu + 300rb tahun ini),
        // beban total 120rb -> laba 380rb.
        $mk = function (string $ref, string $date, array $items) {
            $j = Journal::create(['date' => $date, 'description' => $ref, 'reference' => $ref, 'total_amount' => 0, 'type' => 'general']);
            foreach ($items as $it) {
                JournalItem::create(['journal_id' => $j->id, 'account_id' => $it[0], 'debit' => $it[1], 'credit' => $it[2]]);
            }
        };
        $mk('REV-OLD', now()->subYear()->toDateString(), [[$acc('2002'), 200_000, 0], [$acc('4101'), 0, 200_000]]);
        $mk('REV-NEW', now()->toDateString(), [[$acc('2002'), 300_000, 0], [$acc('4101'), 0, 300_000]]);
        $mk('EXP', now()->toDateString(), [[$acc('5101'), 120_000, 0], [$acc('1002'), 0, 120_000]]);

        $res = $this->actingAs($this->cfo)->get(route('finance.index'))->assertOk();

        $this->assertEquals(500_000.0, $res->viewData('revenueTotal'));
        $this->assertEquals(120_000.0, $res->viewData('expenseTotal'));
        $this->assertEquals(380_000.0, $res->viewData('profitTotal'));
        $this->assertEquals(300_000.0, $res->viewData('revenueYtd'));
        $this->assertEquals(180_000.0, $res->viewData('profitYtd'));
        // margin = 380rb / 500rb = 76%
        $this->assertEquals(76.0, $res->viewData('profitMarginTotal'));
        $this->assertEquals(60.0, $res->viewData('profitMarginYtd')); // 180rb / 300rb
        $res->assertSee('Ringkasan Laba–Rugi');
        $res->assertSee('76.0%');
    }

    #[Test]
    public function non_cfo_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->role = 'admin';
        $admin->save();

        // Admin should be forbidden from CFO routes
        $this->actingAs($admin)
            ->get(route('finance.index'))
            ->assertForbidden();
    }
}
