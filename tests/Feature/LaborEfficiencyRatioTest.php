<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DLER (Direct Labor Efficiency Ratio) — konsep Greg Crabtree diterapkan ke
 * FinancialReportService::directLaborEfficiency(). Dashboard CFO menampilkan
 * panel ini (lihat AUDIT_REPORT.md untuk kenapa MLER belum ikut dihitung).
 */
class LaborEfficiencyRatioTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $acc;

    private FinancialReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->acc = app(AccountingService::class);
        $this->reports = app(FinancialReportService::class);
    }

    private function j(string $date, string $ref, array $items): void
    {
        $this->acc->createJournal($date, $ref, $ref, $items);
    }

    #[Test]
    public function dler_equals_gross_margin_over_direct_labor_for_a_concrete_scenario(): void
    {
        // Pendapatan bersih periode: 10.000.000 (2 pengakuan pendapatan dari
        // Deferred Revenue → Revenue, non-kas, tapi tetap masuk P&L).
        $this->j('2026-04-05', 'REV-1', [
            ['account_code' => '2002', 'debit' => 6_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 6_000_000],
        ]);
        $this->j('2026-04-12', 'REV-2', [
            ['account_code' => '2002', 'debit' => 4_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 4_000_000],
        ]);

        // Biaya tutor periode: honor lepas (5001) 2.000.000 + gaji tutor tetap
        // (5006) 1.500.000 = 3.500.000 total Direct Labor.
        $this->j('2026-04-06', 'FEE-1', [
            ['account_code' => '5001', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 2_000_000],
        ]);
        $this->j('2026-04-20', 'FEE-2', [
            ['account_code' => '5006', 'debit' => 1_500_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 1_500_000],
        ]);

        // Beban non-tutor di periode yang sama TIDAK boleh mempengaruhi DLER
        // (bukan Direct Labor) — pastikan tercampur di data tapi diabaikan.
        $this->j('2026-04-08', 'RENT-1', [
            ['account_code' => '5105', 'debit' => 900_000, 'credit' => 0],
            ['account_code' => '1002', 'debit' => 0, 'credit' => 900_000],
        ]);

        $result = $this->reports->directLaborEfficiency('2026-04-01', '2026-04-30');

        // Rumus manual: Margin Kotor = Pendapatan Bersih (10.000.000) − Biaya
        // Tutor (3.500.000) = 6.500.000. DLER = 6.500.000 / 3.500.000 = 1.857...
        $this->assertEqualsWithDelta(10_000_000, $result['net_revenue'], 0.01);
        $this->assertEqualsWithDelta(3_500_000, $result['direct_labor_cost'], 0.01);
        $this->assertEqualsWithDelta(6_500_000, $result['gross_margin'], 0.01);
        $this->assertEqualsWithDelta(round(6_500_000 / 3_500_000, 2), $result['dler'], 0.01);

        // Skenario ini jatuh di zona "Perlu Perhatian" (1,5x – 2x), bukan
        // "Sehat" (>=2x) atau "Bahaya" (<1,5x) — mengunci ambang warna di UI.
        $this->assertGreaterThanOrEqual(1.5, $result['dler']);
        $this->assertLessThan(2.0, $result['dler']);
    }

    #[Test]
    public function dler_is_null_when_no_direct_labor_cost_is_recorded_in_period(): void
    {
        // Pendapatan tercatat, tapi TIDAK ADA biaya tutor di periode ini —
        // DLER harus null (pembagi nol), bukan 0 atau "efisiensi tak hingga".
        $this->j('2026-05-05', 'REV-1', [
            ['account_code' => '2002', 'debit' => 1_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $result = $this->reports->directLaborEfficiency('2026-05-01', '2026-05-31');

        $this->assertEqualsWithDelta(1_000_000, $result['net_revenue'], 0.01);
        $this->assertEqualsWithDelta(0, $result['direct_labor_cost'], 0.01);
        $this->assertNull($result['dler']);
    }

    #[Test]
    public function dashboard_shows_the_dler_panel_for_a_cfo(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);

        $this->j('2026-06-05', 'REV-1', [
            ['account_code' => '2002', 'debit' => 4_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 4_000_000],
        ]);
        $this->j('2026-06-06', 'FEE-1', [
            ['account_code' => '5001', 'debit' => 1_000_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $response = $this->actingAs($cfo)->get(route('finance.index', ['period' => 'custom', 'from' => '2026-06-01', 'to' => '2026-06-30']));

        $response->assertOk();
        $response->assertSee('Efisiensi Tenaga Kerja Tutor (DLER)');
        $response->assertViewHas('figures', function ($figures) {
            return $figures['ler']['dler'] === 3.0
                && $figures['ler']['direct_labor_cost'] === 1_000_000.0
                && $figures['ler']['gross_margin'] === 3_000_000.0;
        });
    }
}
