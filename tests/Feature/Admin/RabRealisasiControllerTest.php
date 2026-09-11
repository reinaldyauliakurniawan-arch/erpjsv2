<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Rab;
use App\Models\RabMonthlyActual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Realisasi RAB — halaman tunggal (bulanan + kuartalan + grafik), khusus CFO.
 */
class RabRealisasiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cfo = User::factory()->create(['role' => 'cfo']);
        Account::factory()->create(['code' => '5001', 'name' => 'Beban Gaji Tutor', 'type' => 'Expense']);
    }

    private function seedRab(): Rab
    {
        return Rab::create([
            'year' => 2026, 'division' => 'OPERATION', 'account_name' => 'Beban Tutor', 'account_code' => '5001',
            'rab_prev' => 280_000_000, 'annual_budget' => 245_000_000,
            'q1' => 66_000_000, 'q2' => 48_000_000, 'q3' => 70_000_000, 'q4' => 30_000_000,
        ]);
    }

    #[Test]
    public function cfo_sees_annual_budget_quarterly_kpis_and_chart_data(): void
    {
        $this->seedRab();
        foreach ([1 => 15_000_000, 2 => 18_000_000, 3 => 11_000_000] as $m => $amt) {
            RabMonthlyActual::create(['year' => 2026, 'account_code' => '5001', 'month' => $m, 'amount' => $amt]);
        }
        RabMonthlyActual::create(['year' => 2026, 'account_code' => '__REVENUE__', 'month' => 1, 'amount' => 100_000_000]);
        RabMonthlyActual::create(['year' => 2026, 'account_code' => '__REVENUE_TARGET__', 'month' => 1, 'amount' => 120_000_000]);

        $res = $this->actingAs($this->cfo)
            ->get(route('finance.rab-realisasi.index', ['year' => 2026]))
            ->assertOk()
            ->assertViewIs('admin.rab-realisasi.index')
            ->assertSee('Beban Tutor');

        $row = collect($res->viewData('rows'))->firstWhere('account_code', '5001');
        $this->assertSame(245_000_000, $row['budget_total']);   // annual_budget, bukan q sum
        $this->assertSame(44_000_000, $row['real_total']);       // 15+18+11 jt
        $this->assertSame(44_000_000, $row['real_q1']);          // Jan+Feb+Mar
        $this->assertSame(0, $row['real_q2']);

        // Phasing kuartal di-scale ke anggaran tahunan: q1 245jt × 66/214 ≈ 75,56jt.
        $expectedBudgetQ1 = (int) round(245_000_000 * (66 / 214));
        $this->assertSame($expectedBudgetQ1, $row['budget_q1']);

        $q1 = $res->viewData('quarters')[0];
        $this->assertSame($expectedBudgetQ1, $q1['budget']);
        $this->assertSame(
            round(44_000_000 / $expectedBudgetQ1 * 100, 1),
            $q1['budget_realization']                            // realisasi q1 vs anggaran q1 (ter-scale)
        );
        $this->assertSame(83.3, $q1['revenue_achievement']);     // 100jt / 120jt

        $charts = $res->viewData('charts');
        $this->assertSame([100_000_000, 0, 0], array_slice($charts['revenue'], 0, 3));
        $this->assertSame(44_000_000, array_sum($charts['expense']));
    }

    #[Test]
    public function annual_budget_without_quarterly_phasing_splits_evenly_instead_of_vanishing(): void
    {
        // Baris RAB baru: CFO baru mengisi angka tahunan, belum sempat
        // membagi rencana per kuartal (q1..q4 masih 0/kosong). Anggaran
        // bulanan/kuartalan HARUS tetap tersebar rata (annual/12, annual/4),
        // bukan diam-diam jadi Rp 0 di semua bulan.
        Rab::create([
            'year' => 2026, 'division' => 'OPERATION', 'account_name' => 'Beban Baru', 'account_code' => '5001',
            'rab_prev' => 0, 'annual_budget' => 120_000_000,
            'q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0,
        ]);

        $res = $this->actingAs($this->cfo)
            ->get(route('finance.rab-realisasi.index', ['year' => 2026]))
            ->assertOk();

        $row = collect($res->viewData('rows'))->firstWhere('account_code', '5001');
        $this->assertSame(120_000_000, $row['budget_total']);
        $this->assertSame(30_000_000, $row['budget_q1'], 'kuartal harus tersebar rata (120jt / 4), bukan 0');
        $this->assertSame(30_000_000, $row['budget_q2']);
        $this->assertSame(30_000_000, $row['budget_q3']);
        $this->assertSame(30_000_000, $row['budget_q4']);
        $this->assertGreaterThan(0, $row['budget_to_date'], 'anggaran sampai bulan berjalan tidak boleh Rp 0');
    }

    #[Test]
    public function cfo_can_save_actuals_revenue_and_target(): void
    {
        $this->seedRab();

        $this->actingAs($this->cfo)
            ->postJson(route('finance.rab-realisasi.actuals'), [
                'year' => 2026,
                'actuals' => [
                    ['account_code' => '5001', 'month' => 5, 'amount' => 20_000_000],
                    ['account_code' => '__REVENUE__', 'month' => 5, 'amount' => 90_000_000],
                    ['account_code' => '__REVENUE_TARGET__', 'month' => 5, 'amount' => 95_000_000],
                ],
            ])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('rab_monthly_actuals', ['account_code' => '5001', 'month' => 5, 'amount' => 20_000_000]);
        $this->assertDatabaseHas('rab_monthly_actuals', ['account_code' => '__REVENUE__', 'month' => 5, 'amount' => 90_000_000]);
        $this->assertDatabaseHas('rab_monthly_actuals', ['account_code' => '__REVENUE_TARGET__', 'month' => 5, 'amount' => 95_000_000]);
    }

    #[Test]
    public function saving_twice_updates_instead_of_duplicating(): void
    {
        $this->seedRab();
        $save = fn ($amt) => $this->actingAs($this->cfo)->postJson(route('finance.rab-realisasi.actuals'), [
            'year' => 2026, 'actuals' => [['account_code' => '5001', 'month' => 6, 'amount' => $amt]],
        ]);
        $save(1_000_000)->assertOk();
        $save(2_000_000)->assertOk();

        $this->assertSame(1, RabMonthlyActual::where('account_code', '5001')->where('month', 6)->count());
        $this->assertSame(2_000_000, (int) RabMonthlyActual::where('account_code', '5001')->where('month', 6)->value('amount'));
    }

    #[Test]
    public function admin_and_tutor_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('finance.rab-realisasi.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'tutor']))
            ->post(route('finance.rab-realisasi.actuals'), ['year' => 2026, 'actuals' => []])->assertForbidden();
    }

    #[Test]
    public function the_old_rab_tracker_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('finance.rab-tracker.index'));
    }
}
