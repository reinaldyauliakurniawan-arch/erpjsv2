<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountCode;
use App\Models\User;
use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dashboard CFO: filter periode + kartu Posisi Keuangan / Arus Kas.
 *
 * Jaminan utama: angka periode di dashboard SAMA PERSIS dengan angka di halaman
 * Laporan (P&L, Arus Kas, Neraca) untuk periode yang sama — karena keduanya
 * memakai FinancialReportService yang sama. Test ini mengunci itu supaya tidak
 * ada laporan yang menyimpang kalau rumus akuntansi diubah.
 */
class FinanceDashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->cfo = User::factory()->create(['role' => 'cfo']);
        $this->seedLedger();
    }

    /** Beberapa transaksi tersebar di 2026 supaya periode berbeda beda angkanya. */
    private function seedLedger(): void
    {
        $acc = app(AccountingService::class);
        // Jan: pembayaran siswa (kas naik), pengakuan pendapatan, honor tutor.
        $acc->createJournal('2026-01-10', 'DP', 'T-1', [
            ['account_code' => AccountCode::BANK->value, 'debit' => 5_000_000, 'credit' => 0],
            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => 5_000_000],
        ]);
        $acc->createJournal('2026-01-20', 'Rev rec', 'T-2', [
            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 1_200_000, 'credit' => 0],
            ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => 1_200_000],
        ]);
        $acc->createJournal('2026-01-25', 'Honor', 'T-3', [
            ['account_code' => AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => 400_000, 'credit' => 0],
            ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => 0, 'credit' => 400_000],
        ]);
        // Feb: bayar honor tutor (kas turun), pengakuan pendapatan lagi.
        $acc->createJournal('2026-02-05', 'Payroll', 'T-4', [
            ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => 400_000, 'credit' => 0],
            ['account_code' => AccountCode::BANK->value, 'debit' => 0, 'credit' => 400_000],
        ]);
        $acc->createJournal('2026-02-15', 'Rev rec', 'T-5', [
            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 900_000, 'credit' => 0],
            ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => 900_000],
        ]);
        // Mar: pembayaran siswa lain.
        $acc->createJournal('2026-03-12', 'DP2', 'T-6', [
            ['account_code' => AccountCode::CASH->value, 'debit' => 3_000_000, 'credit' => 0],
            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => 3_000_000],
        ]);
    }

    private function dashboardData(array $params): array
    {
        return $this->actingAs($this->cfo)
            ->getJson(route('finance.dashboard-data', $params))
            ->assertOk()
            ->json();
    }

    #[Test]
    public function dashboard_period_figures_match_the_reports_pages_for_the_same_period(): void
    {
        $params = ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-03-31'];

        $dash = $this->dashboardData($params);

        // ── P&L ──
        $pl = $this->actingAs($this->cfo)->get(route('finance.reports.profit-loss', $params));
        $plNetProfit = (float) $pl->viewData('netProfit');
        $plNetRevenue = (float) $pl->viewData('totalRevenue') - (float) $pl->viewData('totalContra');
        $plExpense = (float) $pl->viewData('totalExpense');

        $this->assertEqualsWithDelta($plNetProfit, $dash['net_profit'], 0.01, 'net profit dashboard vs P&L');
        $this->assertEqualsWithDelta($plNetRevenue, $dash['revenue'], 0.01, 'revenue dashboard vs P&L');
        $this->assertEqualsWithDelta($plExpense, $dash['expense'], 0.01, 'expense dashboard vs P&L');

        // ── Arus Kas ──
        $cf = $this->actingAs($this->cfo)->get(route('finance.reports.cash-flow', $params));
        $this->assertEqualsWithDelta((float) $cf->viewData('netChange'), $dash['net_cash_flow'], 0.01, 'net cash flow dashboard vs Cash Flow report');

        // ── Neraca (as of akhir periode) ──
        $bs = $this->actingAs($this->cfo)->get(route('finance.reports.balance-sheet', ['as_of' => '2026-03-31']));
        $this->assertEqualsWithDelta((float) $bs->viewData('totalAsset'), $dash['total_asset'], 0.01, 'total asset');
        $this->assertEqualsWithDelta((float) $bs->viewData('totalLiability'), $dash['total_liability'], 0.01, 'total liability');
        $this->assertEqualsWithDelta((float) $bs->viewData('totalEquity'), $dash['total_equity'], 0.01, 'total equity');
    }

    #[Test]
    public function preset_periods_return_different_figures(): void
    {
        $jan = $this->dashboardData(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31']);
        $feb = $this->dashboardData(['period' => 'custom', 'from' => '2026-02-01', 'to' => '2026-02-28']);

        // Jan: revenue 1.2jt, beban 400rb, laba 800rb.
        $this->assertEqualsWithDelta(1_200_000, $jan['revenue'], 0.01);
        $this->assertEqualsWithDelta(400_000, $jan['expense'], 0.01);
        $this->assertEqualsWithDelta(800_000, $jan['net_profit'], 0.01);
        // Feb: revenue 900rb, beban 0.
        $this->assertEqualsWithDelta(900_000, $feb['revenue'], 0.01);
        $this->assertEqualsWithDelta(0, $feb['expense'], 0.01);

        $this->assertNotEquals($jan['net_cash_flow'], $feb['net_cash_flow']);
    }

    #[Test]
    public function last_month_preset_resolves_to_the_previous_calendar_month(): void
    {
        $d = $this->dashboardData(['period' => 'last_month']);

        $this->assertSame('last_month', $d['period']);
        $this->assertSame('Bulan Lalu', $d['period_label']);
        $this->assertSame(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), $d['from']);
        $this->assertSame(now()->subMonthNoOverflow()->endOfMonth()->toDateString(), $d['to']);
    }

    #[Test]
    public function all_time_preset_includes_every_journal_ever_posted(): void
    {
        $d = $this->dashboardData(['period' => 'all']);

        $this->assertSame('all', $d['period']);
        $this->assertSame('Semua Waktu', $d['period_label']);
        // Seluruh transaksi seedLedger(): Jan (1.2jt) + Feb (900rb) revenue, 400rb beban.
        $this->assertEqualsWithDelta(2_100_000, $d['revenue'], 0.01);
        $this->assertEqualsWithDelta(400_000, $d['expense'], 0.01);
    }

    #[Test]
    public function dashboard_net_cash_flow_equals_the_cash_flow_report_net_change(): void
    {
        $params = ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-03-31'];
        $dash = $this->dashboardData($params);

        $cf = $this->actingAs($this->cfo)->get(route('finance.reports.cash-flow', $params));
        $reportNetChange = (float) $cf->viewData('netOperating')
            + (float) $cf->viewData('netInvesting')
            + (float) $cf->viewData('netFinancing');

        $this->assertEqualsWithDelta($reportNetChange, $dash['net_cash_flow'], 0.01);
        $this->assertEqualsWithDelta((float) $cf->viewData('netChange'), $dash['net_cash_flow'], 0.01);
    }

    #[Test]
    public function trend_series_sums_to_the_period_totals(): void
    {
        $d = $this->dashboardData(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-03-31']);

        $this->assertEqualsWithDelta($d['revenue'], array_sum($d['trend']['revenue']), 0.5, 'jumlah seri revenue = total periode');
        $this->assertEqualsWithDelta($d['expense'], array_sum($d['trend']['expense']), 0.5, 'jumlah seri beban = total periode');
        $this->assertEqualsWithDelta($d['net_cash_flow'], array_sum($d['cash_flow_series']['net']), 0.5, 'jumlah seri arus kas = net cash flow periode');
    }

    #[Test]
    public function dashboard_page_renders_with_the_new_cards(): void
    {
        $this->actingAs($this->cfo)->get(route('finance.index'))
            ->assertOk()
            ->assertViewIs('admin.finance.dashboard')
            ->assertSee('Posisi Keuangan')
            ->assertSee('Tren Keuangan')
            ->assertViewHas('figures');
    }

    #[Test]
    public function admin_cannot_reach_the_finance_dashboard_data_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson(route('finance.dashboard-data'))->assertForbidden();
    }
}
