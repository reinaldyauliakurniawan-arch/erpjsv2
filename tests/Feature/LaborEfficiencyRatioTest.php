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
        $response->assertSee('Efisiensi Tenaga Kerja (LER)');
        $response->assertSee('Tenaga pengajar (DLER)');
        $response->assertSee('Total LER (efisiensi tenaga kerja keseluruhan)');
        $response->assertViewHas('figures', function ($figures) {
            return $figures['ler']['dler'] === 3.0
                && $figures['ler']['direct_labor_cost'] === 1_000_000.0
                && $figures['ler']['gross_margin'] === 3_000_000.0
                // MLER belum bisa dihitung → Total LER juga harus null, MESKI
                // DLER-nya sudah ada — tidak boleh diam-diam pakai DLER saja.
                && $figures['ler']['mler'] === null
                && $figures['ler']['total_ler'] === null;
        });
    }

    // ═══ Total LER = DLER + MLER (null-propagation terpusat di service) ═══

    #[Test]
    public function management_labor_efficiency_always_returns_null_for_now(): void
    {
        // Akun gaji admin/manajemen belum pernah dipakai mencatat transaksi
        // di buku besar sama sekali → MLER SENGAJA selalu null, apa pun
        // periodenya, sampai data itu tersedia (lihat AUDIT_REPORT.md).
        $this->assertNull($this->reports->managementLaborEfficiency('2026-01-01', '2026-12-31'));
    }

    #[Test]
    public function combine_labor_efficiency_returns_null_when_either_component_is_null(): void
    {
        $this->assertNull($this->reports->combineLaborEfficiency(2.5, null));
        $this->assertNull($this->reports->combineLaborEfficiency(null, 1.2));
        $this->assertNull($this->reports->combineLaborEfficiency(null, null));
    }

    #[Test]
    public function combine_labor_efficiency_sums_both_components_when_both_available(): void
    {
        $this->assertEqualsWithDelta(3.0, $this->reports->combineLaborEfficiency(1.86, 1.14), 0.01);
        $this->assertEqualsWithDelta(4.36, $this->reports->combineLaborEfficiency(2.5, 1.86), 0.01);
    }

    #[Test]
    public function total_ler_stays_null_while_dler_alone_is_available(): void
    {
        // Skenario dengan DLER nyata (bukan null) — memastikan laborEfficiency()
        // TIDAK diam-diam menjadikan DLER sebagai Total LER selama MLER masih
        // null. Ini mengunci perilaku end-to-end lewat method gabungan yang
        // dipakai FinanceController, bukan cuma unit combineLaborEfficiency().
        $this->j('2026-07-05', 'REV-1', [
            ['account_code' => '2002', 'debit' => 5_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 5_000_000],
        ]);
        $this->j('2026-07-06', 'FEE-1', [
            ['account_code' => '5001', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 2_000_000],
        ]);

        $result = $this->reports->laborEfficiency('2026-07-01', '2026-07-31');

        $this->assertNotNull($result['dler']);
        $this->assertNull($result['mler']);
        $this->assertNull($result['total_ler']);
    }
}
