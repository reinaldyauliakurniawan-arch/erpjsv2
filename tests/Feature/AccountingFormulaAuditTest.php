<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\FixedAsset;
use App\Models\Program;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AttendanceService;
use App\Services\DepreciationService;
use App\Services\EnrollmentLedgerService;
use App\Services\FinancialReportService;
use App\Services\RevenueRecognitionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit rumus akuntansi (lihat AUDIT_REPORT.md). Setiap test mengunci satu
 * perbaikan dengan skenario transaksi konkret.
 */
class AccountingFormulaAuditTest extends TestCase
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

    /** Saldo kas aktual (akun 1001 + 1002) per tanggal. */
    private function actualCash(?string $asOf = null): float
    {
        return (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.code', ['1001', '1002'])
            ->when($asOf, fn ($q) => $q->whereDate('journals.date', '<=', $asOf))
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) v')->value('v') ?? 0;
    }

    // ═══ BUG 1 — Cash Flow double-count pada transaksi akrual ═══════════════

    #[Test]
    public function pure_accrual_journal_does_not_move_cash_flow(): void
    {
        // Honor tutor dicatat, BELUM dibayar tunai: Dr Beban / Cr Utang Tutor.
        $this->j('2026-03-10', 'ACC-1', [
            ['account_code' => '5001', 'debit' => 500_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 500_000],
        ]);

        $cf = $this->reports->cashFlow('2026-03-01', '2026-03-31');

        // Kas belum bergerak → netChange = 0 (bukan +500k / −500k / double).
        $this->assertEqualsWithDelta(0, $cf['netChange'], 0.01);
        $this->assertEqualsWithDelta(0, $cf['netOperating'], 0.01);
        $this->assertEqualsWithDelta(0, $cf['unclassified'], 0.01);
    }

    #[Test]
    public function cash_flow_reconciles_to_actual_cash_movement_in_every_scenario(): void
    {
        // Campuran: setoran modal (kas masuk), pembayaran siswa (Deferred naik),
        // beban dibayar tunai, honor akrual (non-kas), beli aset (kas keluar),
        // penyusutan (non-kas), pelunasan utang tutor (kas keluar).
        $this->j('2026-01-05', 'M-1', [ // setoran modal
            ['account_code' => '1002', 'debit' => 50_000_000, 'credit' => 0],
            ['account_code' => '3001', 'debit' => 0, 'credit' => 50_000_000],
        ]);
        $this->j('2026-01-10', 'M-2', [ // pembayaran siswa
            ['account_code' => '1001', 'debit' => 6_000_000, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => 6_000_000],
        ]);
        $this->j('2026-01-15', 'M-3', [ // pengakuan pendapatan (non-kas)
            ['account_code' => '2002', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 2_000_000],
        ]);
        $this->j('2026-01-18', 'M-4', [ // beban sewa dibayar tunai
            ['account_code' => '5105', 'debit' => 3_000_000, 'credit' => 0],
            ['account_code' => '1002', 'debit' => 0, 'credit' => 3_000_000],
        ]);
        $this->j('2026-01-20', 'M-5', [ // honor akrual (non-kas)
            ['account_code' => '5001', 'debit' => 800_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 800_000],
        ]);
        $this->j('2026-01-22', 'M-6', [ // beli peralatan tunai
            ['account_code' => '1005', 'debit' => 12_000_000, 'credit' => 0],
            ['account_code' => '1002', 'debit' => 0, 'credit' => 12_000_000],
        ]);
        $this->j('2026-01-28', 'M-7', [ // penyusutan (non-kas)
            ['account_code' => '5108', 'debit' => 500_000, 'credit' => 0],
            ['account_code' => '1006', 'debit' => 0, 'credit' => 500_000],
        ]);
        $this->j('2026-01-30', 'M-8', [ // bayar utang tutor
            ['account_code' => '2003', 'debit' => 800_000, 'credit' => 0],
            ['account_code' => '1001', 'debit' => 0, 'credit' => 800_000],
        ]);

        $cf = $this->reports->cashFlow('2026-01-01', '2026-01-31');

        $actualMovement = $this->actualCash('2026-01-31') - 0.0; // kas awal 0

        // IDENTITAS WAJIB: cashOpening + netChange === cashEnding (aktual).
        $this->assertEqualsWithDelta(0, $cf['cashOpening'], 0.01);
        $this->assertEqualsWithDelta($actualMovement, $cf['cashEnding'], 0.01);
        $this->assertEqualsWithDelta($cf['cashEnding'], $cf['cashOpening'] + $cf['netChange'], 0.01);

        // Bagian-bagiannya menjumlah ke netChange, tanpa sisa.
        $this->assertEqualsWithDelta(
            $cf['netChange'],
            $cf['netOperating'] + $cf['netInvesting'] + $cf['netFinancing'] + $cf['unclassified'],
            0.01
        );
        $this->assertEqualsWithDelta(0, $cf['unclassified'], 0.01, 'tidak ada yang belum terkategori');

        // Nilai konkret: kas masuk 50jt + 6jt, keluar 3jt + 12jt + 0.8jt.
        $this->assertEqualsWithDelta(50_000_000 + 6_000_000 - 3_000_000 - 12_000_000 - 800_000, $cf['netChange'], 0.01);
        $this->assertEqualsWithDelta(50_000_000, $cf['netFinancing'], 0.01);
        $this->assertEqualsWithDelta(-12_000_000, $cf['netInvesting'], 0.01); // beli peralatan; penyusutan non-kas dikeluarkan
    }

    #[Test]
    public function operating_cash_flow_equals_net_profit_plus_depreciation_plus_working_capital(): void
    {
        $this->j('2026-02-10', 'O-1', [
            ['account_code' => '1001', 'debit' => 10_000_000, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => 10_000_000],
        ]);
        $this->j('2026-02-15', 'O-2', [
            ['account_code' => '2002', 'debit' => 4_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 4_000_000],
        ]);
        $this->j('2026-02-20', 'O-3', [
            ['account_code' => '5108', 'debit' => 600_000, 'credit' => 0],
            ['account_code' => '1006', 'debit' => 0, 'credit' => 600_000],
        ]);
        $this->j('2026-02-25', 'O-4', [ // honor akrual
            ['account_code' => '5001', 'debit' => 1_000_000, 'credit' => 0],
            ['account_code' => '2003', 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $cf = $this->reports->cashFlow('2026-02-01', '2026-02-28');
        $pl = $this->reports->profitLoss('2026-02-01', '2026-02-28');

        $expected = $pl['netProfit'] + $cf['depreciationAddBack'] + $cf['netWorkingCapital'];
        $this->assertEqualsWithDelta($expected, $cf['netOperating'], 0.01);
        // Penyusutan 600k ditambahkan kembali (beban non-kas).
        $this->assertEqualsWithDelta(600_000, $cf['depreciationAddBack'], 0.01);
    }

    // ═══ BUG 2 — Contra-revenue (4111) harus MENGURANGI laba ═══════════════

    #[Test]
    public function contra_revenue_reduces_net_profit_and_net_revenue_everywhere(): void
    {
        // Pendapatan 10jt, lalu beri diskon 1,5jt (Dr 4111 / Cr Kas).
        $this->j('2026-04-05', 'R-1', [
            ['account_code' => '1001', 'debit' => 10_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 10_000_000],
        ]);
        $this->j('2026-04-06', 'R-2', [
            ['account_code' => '4111', 'debit' => 1_500_000, 'credit' => 0],
            ['account_code' => '1001', 'debit' => 0, 'credit' => 1_500_000],
        ]);

        $pl = $this->reports->profitLoss('2026-04-01', '2026-04-30');

        $this->assertEqualsWithDelta(10_000_000, $pl['totalRevenue'], 0.01, 'pendapatan bruto');
        $this->assertEqualsWithDelta(1_500_000, $pl['totalContra'], 0.01, 'contra bertanda POSITIF');
        $this->assertEqualsWithDelta(8_500_000, $pl['netRevenue'], 0.01, 'pendapatan bersih = bruto − diskon');
        $this->assertEqualsWithDelta(8_500_000, $pl['netProfit'], 0.01, 'diskon MENGURANGI laba (bukan menambah)');

        // Neraca tetap balance walau ada contra-revenue.
        $bs = $this->reports->balanceSheet('2026-04-30');
        $this->assertTrue($bs['isBalanced']);
        $this->assertEqualsWithDelta($bs['totalAsset'], $bs['totalLiability'] + $bs['totalEquity'], 0.01);

        // Tren juga: pendapatan bersih & laba bersih ikut turun.
        $trend = $this->reports->trendSeries('2026-04-01', '2026-04-30', 'month');
        $this->assertEqualsWithDelta(8_500_000, array_sum($trend['revenue']), 0.5);
        $this->assertEqualsWithDelta(8_500_000, array_sum($trend['netProfit']), 0.5);

        // General Ledger & Adjusted Trial Balance juga harus memperlakukan
        // 4111 sebagai debit-normal (saldo +1.500.000), bukan credit-normal
        // (yang akan tampil sebagai -1.500.000 — terbalik/membingungkan CFO).
        $cfo = User::factory()->create(['role' => 'cfo']);
        $glRes = $this->actingAs($cfo)->get(route('finance.reports.general-ledger', ['from' => '2026-04-01', 'to' => '2026-04-30']));
        $glRow = collect($glRes->viewData('ledger'))->firstWhere('code', '4111');
        $this->assertNotNull($glRow);
        $this->assertTrue($glRow['normal_debet'], 'akun 4111 harus diperlakukan debit-normal di General Ledger');
        $this->assertEqualsWithDelta(1_500_000, $glRow['final_balance'], 0.01);

        $atbRes = $this->actingAs($cfo)->get(route('finance.reports.adjusted-trial-balance', ['from' => '2026-04-01', 'to' => '2026-04-30']));
        $atbRow = collect($atbRes->viewData('rows'))->firstWhere('code', '4111');
        $this->assertNotNull($atbRow);
        $this->assertEqualsWithDelta(1_500_000, $atbRow->pre_saldo, 0.01, 'saldo 4111 di Adjusted Trial Balance harus positif (debit-normal)');
    }

    // ═══ BUG 3 — Neraca selalu balance untuk sembarang tanggal ════════════

    #[Test]
    public function balance_sheet_is_balanced_for_any_date_including_across_years(): void
    {
        // Tahun 2025: laba 5jt (belum ada jurnal penutup).
        $this->j('2025-06-01', 'Y1-1', [
            ['account_code' => '1002', 'debit' => 8_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 8_000_000],
        ]);
        $this->j('2025-07-01', 'Y1-2', [
            ['account_code' => '5001', 'debit' => 3_000_000, 'credit' => 0],
            ['account_code' => '1002', 'debit' => 0, 'credit' => 3_000_000],
        ]);
        // Tahun 2026: transaksi lagi.
        $this->j('2026-02-01', 'Y2-1', [
            ['account_code' => '1001', 'debit' => 4_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 4_000_000],
        ]);
        $this->j('2026-03-01', 'Y2-2', [
            ['account_code' => '1005', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '1001', 'debit' => 0, 'credit' => 2_000_000],
        ]);

        foreach (['2025-06-15', '2025-12-31', '2026-01-01', '2026-02-15', '2026-06-30'] as $asOf) {
            $bs = $this->reports->balanceSheet($asOf);
            $this->assertEqualsWithDelta(
                $bs['totalAsset'],
                $bs['totalLiability'] + $bs['totalEquity'],
                0.01,
                "Neraca harus balance per {$asOf}"
            );
            $this->assertTrue($bs['isBalanced']);
        }

        // Laba akumulatif (2025 + s.d. tanggal 2026) ikut ke ekuitas.
        $bs = $this->reports->balanceSheet('2026-12-31');
        $this->assertEqualsWithDelta(5_000_000 + 4_000_000, $bs['retainedAndCurrent'], 0.01);
    }

    #[Test]
    public function equity_statement_end_balance_matches_the_balance_sheet_equity(): void
    {
        $this->j('2025-05-01', 'E-1', [ // modal awal
            ['account_code' => '1002', 'debit' => 30_000_000, 'credit' => 0],
            ['account_code' => '3001', 'debit' => 0, 'credit' => 30_000_000],
        ]);
        $this->j('2025-08-01', 'E-2', [ // laba 2025
            ['account_code' => '1002', 'debit' => 7_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 7_000_000],
        ]);
        $this->j('2026-03-01', 'E-3', [ // laba 2026
            ['account_code' => '1001', 'debit' => 9_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 9_000_000],
        ]);
        $this->j('2026-04-01', 'E-4', [ // beban 2026
            ['account_code' => '5001', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '1001', 'debit' => 0, 'credit' => 2_000_000],
        ]);

        $cfo = User::factory()->create(['role' => 'cfo']);
        $eq = $this->actingAs($cfo)->get(route('finance.reports.equity-statement', ['year' => 2026]));
        $modalAkhir = (float) $eq->viewData('modalAkhir');

        $bs = $this->reports->balanceSheet('2026-12-31');

        $this->assertEqualsWithDelta($bs['totalEquity'], $modalAkhir, 0.01);
        // 30jt modal + 7jt laba 2025 + (9jt − 2jt) laba 2026 = 44jt.
        $this->assertEqualsWithDelta(44_000_000, $modalAkhir, 0.01);
    }

    // ═══ BUG 4 — Pembulatan revenue recognition ═══════════════════════════

    #[Test]
    public function full_revenue_recognition_equals_contract_amount_exactly(): void
    {
        // 1.000.000 / 3 pertemuan = 333.333,33 → 3× = 999.999,99 (rumus lama
        // menyisakan 0,01 di Deferred Revenue selamanya).
        [$enrollment, $tutorUser] = $this->activeEnrollment(3, 1_000_000);
        $this->mark($enrollment, $tutorUser, '2026-01-06');
        $this->mark($enrollment, $tutorUser, '2026-01-13');
        $this->mark($enrollment, $tutorUser, '2026-01-20');

        $rr = app(RevenueRecognitionService::class);
        $fresh = $enrollment->fresh()->load('program');

        $recognized = (float) $rr->totalRevenueRecognizedSoFar($fresh);
        $this->assertEqualsWithDelta(1_000_000, $recognized, 0.001, 'revenue diakui == total kontrak PERSIS');

        // Jumlah kredit di akun Pendapatan (4101) untuk enrollment ini == 1jt.
        $revPosted = (float) DB::table('journal_items as ji')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->join('journals as j', 'j.id', '=', 'ji.journal_id')
            ->where('j.enrollment_id', $enrollment->id)
            ->where('a.code', '4101')
            ->selectRaw('SUM(ji.credit) - SUM(ji.debit) v')->value('v');
        $this->assertEqualsWithDelta(1_000_000, $revPosted, 0.001);

        // Deferred Revenue enrollment ini kembali NOL (tidak ada sisa 0,01).
        $deferred = (float) DB::table('journal_items as ji')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->join('journals as j', 'j.id', '=', 'ji.journal_id')
            ->where('j.enrollment_id', $enrollment->id)
            ->where('a.code', '2002')
            ->selectRaw('SUM(ji.credit) - SUM(ji.debit) v')->value('v');
        $this->assertEqualsWithDelta(0, $deferred, 0.001);

        $this->assertTrue(app(EnrollmentLedgerService::class)->isInSync($fresh));
    }

    // ═══ BUG 5 — Pembulatan penyusutan ═══════════════════════════════════

    #[Test]
    public function total_depreciation_equals_depreciable_base_exactly(): void
    {
        $exp = Account::where('code', '5108')->value('id');
        $accum = Account::where('code', '1006')->value('id');
        // 10.000.000 / 24 = 416.666,67 → 24× = 10.000.000,08 (lebih 8 sen).
        $asset = FixedAsset::create([
            'name' => 'Laptop Audit', 'category' => 'Elektronik',
            'acquired_at' => now()->subMonths(30)->toDateString(),
            'cost' => 10_000_000, 'salvage_value' => 0, 'useful_life' => 24,
            'depreciation_method' => 'straight_line',
            'expense_account_id' => $exp, 'accumulated_account_id' => $accum, 'is_active' => true,
        ]);

        app(DepreciationService::class)->rebuildAsset($asset);

        $totalDepr = (float) DB::table('journal_items as ji')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('a.code', '1006')
            ->selectRaw('SUM(ji.credit) - SUM(ji.debit) v')->value('v');

        $this->assertEqualsWithDelta(10_000_000, $totalDepr, 0.001, 'akumulasi penyusutan == (cost − salvage) PERSIS');

        // Trial balance tetap 0.
        $tb = (float) DB::table('journal_items')->selectRaw('SUM(debit) - SUM(credit) v')->value('v');
        $this->assertEqualsWithDelta(0, $tb, 0.01);
    }

    // ═══ Saldo normal akun konsisten ═════════════════════════════════════

    #[Test]
    public function normal_balances_follow_debit_credit_convention(): void
    {
        // Asset & Expense naik saat DEBIT; Liability/Equity/Revenue naik saat KREDIT.
        $this->j('2026-05-01', 'N-1', [
            ['account_code' => '1001', 'debit' => 5_000_000, 'credit' => 0],   // Asset +
            ['account_code' => '3001', 'debit' => 0, 'credit' => 5_000_000],   // Equity +
        ]);
        $this->j('2026-05-02', 'N-2', [
            ['account_code' => '5001', 'debit' => 1_000_000, 'credit' => 0],   // Expense +
            ['account_code' => '2003', 'debit' => 0, 'credit' => 1_000_000],   // Liability +
        ]);
        $this->j('2026-05-03', 'N-3', [
            ['account_code' => '1001', 'debit' => 2_000_000, 'credit' => 0],   // Asset +
            ['account_code' => '4101', 'debit' => 0, 'credit' => 2_000_000],   // Revenue +
        ]);

        $bs = $this->reports->balanceSheet('2026-05-31');
        $asset = $bs['rows']->firstWhere('code', '1001');
        $equity = $bs['rows']->firstWhere('code', '3001');
        $liab = $bs['rows']->firstWhere('code', '2003');

        $this->assertEqualsWithDelta(7_000_000, $asset->balance, 0.01);   // debit-normal, positif
        $this->assertEqualsWithDelta(5_000_000, $equity->balance, 0.01);  // credit-normal, positif
        $this->assertEqualsWithDelta(1_000_000, $liab->balance, 0.01);    // credit-normal, positif

        $pl = $this->reports->profitLoss('2026-05-01', '2026-05-31');
        $this->assertEqualsWithDelta(2_000_000, $pl['totalRevenue'], 0.01); // credit-normal
        $this->assertEqualsWithDelta(1_000_000, $pl['totalExpense'], 0.01); // debit-normal
        $this->assertEqualsWithDelta(1_000_000, $pl['netProfit'], 0.01);

        $this->assertTrue($bs['isBalanced']);
    }

    // ═══ BUG 6 — Rentang tanggal lebar menghilangkan data dari seri chart ═══

    #[Test]
    public function wide_date_range_does_not_drop_transactions_from_trend_or_cash_flow_series(): void
    {
        // Rentang 7 tahun (mis. filter "Semua Waktu" yang start-nya jauh di
        // masa lalu) HARUS pakai granularitas tahunan (lihat resolvePeriod()),
        // supaya bucket bulanan (di-cap 60 = 5 tahun, dihitung dari $from)
        // tidak diam-diam melewatkan transaksi di luar 5 tahun pertama.
        $this->j('2020-03-01', 'WIDE-REV-1', [
            ['account_code' => '1001', 'debit' => 1_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 1_000_000],
        ]);
        $this->j('2026-06-15', 'WIDE-REV-2', [
            ['account_code' => '1001', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 2_000_000],
        ]);
        $this->j('2026-06-16', 'WIDE-EXP-1', [
            ['account_code' => '5001', 'debit' => 300_000, 'credit' => 0],
            ['account_code' => '1001', 'debit' => 0, 'credit' => 300_000],
        ]);

        $from = '2020-01-01';
        $to = '2026-12-31';
        $p = $this->reports->resolvePeriod('custom', $from, $to);
        $this->assertSame('year', $p['granularity'], 'rentang 7 tahun harus pakai granularitas tahunan');

        $pl = $this->reports->profitLoss($from, $to);
        $trend = $this->reports->trendSeries($from, $to, $p['granularity']);
        $cf = $this->reports->cashFlow($from, $to);
        $cfSeries = $this->reports->cashFlowSeries($from, $to, $p['granularity']);

        $this->assertContains('2020', array_map(fn ($l) => (string) $l, $trend['labels']));
        $this->assertContains('2026', array_map(fn ($l) => (string) $l, $trend['labels']));
        $this->assertEqualsWithDelta($pl['netRevenue'], array_sum($trend['revenue']), 0.01, 'seri tren tidak boleh kehilangan transaksi lama');
        $this->assertEqualsWithDelta($pl['totalExpense'], array_sum($trend['expense']), 0.01);
        $this->assertEqualsWithDelta($cf['netChange'], array_sum($cfSeries['net']), 0.01, 'seri arus kas tidak boleh kehilangan transaksi lama');
        $this->assertEqualsWithDelta(3_000_000, array_sum($trend['revenue']), 0.01);
    }

    #[Test]
    public function ledger_inception_precedes_the_oldest_journal_so_retained_earnings_stays_balanced(): void
    {
        // Jurnal jauh sebelum "awal pembukuan" yang wajar (mis. data migrasi
        // lama yang salah tanggal) TETAP harus ikut terlipat ke laba ditahan,
        // supaya Neraca tidak selisih (lihat komentar LEDGER_INCEPTION).
        $this->j('1950-01-01', 'ANCIENT-1', [
            ['account_code' => '1001', 'debit' => 400_000, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 400_000],
        ]);

        $bs = $this->reports->balanceSheet(now()->toDateString());
        $this->assertTrue($bs['isBalanced'], 'transaksi lebih tua dari LEDGER_INCEPTION harus tetap membuat Neraca balance');
        $this->assertEqualsWithDelta(400_000, $bs['retainedAndCurrent'], 0.01);
    }

    // ═══ BUG 7 — Attendance ekstra di luar kontrak menambah revenue hantu ═══

    #[Test]
    public function extra_attendance_beyond_contracted_meetings_does_not_inflate_recognized_revenue(): void
    {
        [$enrollment, $tutorUser] = $this->activeEnrollment(3, 1_000_000);

        // 3 pertemuan normal, sesuai kontrak.
        $this->mark($enrollment, $tutorUser, '2026-01-06');
        $this->mark($enrollment, $tutorUser, '2026-01-07');
        $this->mark($enrollment, $tutorUser, '2026-01-08');

        // Anomali data (migrasi lama / total_meetings program diubah setelah
        // siswa jalan): 2 baris attendance_student EKSTRA di luar kontrak,
        // dimasukkan langsung ke DB — mensimulasikan data yang bypass guard
        // remaining_meetings di AttendanceService (mis. hasil import).
        for ($k = 0; $k < 2; $k++) {
            $attendance = Attendance::create([
                'class_session_id' => $enrollment->class_session_id,
                'date' => now()->addDays($k + 1)->toDateString(),
                'time_block' => '08:00-09:30',
                'classroom_id' => Classroom::factory()->create()->id,
                'marked_by' => $tutorUser->id,
            ]);
            DB::table('attendance_student')->insert([
                'attendance_id' => $attendance->id,
                'enrollment_id' => $enrollment->id,
                'is_present' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(EnrollmentLedgerService::class)->rebuild($enrollment->fresh());

        $journalIds = DB::table('journals')->where('enrollment_id', $enrollment->id)->pluck('id');
        $totalRevenue = (float) DB::table('journal_items as ji')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('a.code', '4101')
            ->whereIn('ji.journal_id', $journalIds)
            ->selectRaw('SUM(ji.credit) - SUM(ji.debit) v')->value('v');

        $this->assertEqualsWithDelta(1_000_000, $totalRevenue, 0.01, 'revenue enrollment tidak boleh melebihi total_amount kontrak walau ada attendance ekstra');

        $tb = (float) DB::table('journal_items')->selectRaw('SUM(debit) - SUM(credit) v')->value('v');
        $this->assertEqualsWithDelta(0, $tb, 0.01, 'trial balance tetap 0');
    }

    // ── helper ───────────────────────────────────────────────────────────

    /** @return array{0:Enrollment,1:User} */
    private function activeEnrollment(int $meetings, int $total): array
    {
        $program = Program::factory()->create(['total_meetings' => $meetings, 'price' => $total, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorUser->id]);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 50_000]);

        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active',
            'payment_method' => 'full upfront', 'payment_status' => 'full', 'remaining_meetings' => $meetings,
            'total_amount' => $total, 'enrollment_date' => '2026-01-05',
        ]);
        $this->acc->createJournal('2026-01-05', "PAYMENT-ENROLL-{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}", [
            ['account_code' => '1002', 'debit' => $total, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => $total],
        ], 'payment', $program->id, $enrollment->id);

        return [$enrollment, $tutorUser];
    }

    private function mark(Enrollment $e, User $tutorUser, string $date): void
    {
        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $e->class_session_id, 'date' => $date, 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id, 'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $e->id, 'is_present' => true]],
        ]);
    }
}
