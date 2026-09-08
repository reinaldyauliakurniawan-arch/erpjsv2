<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Rab;
use App\Models\RabMonthlyActual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tracker RAB — bulanan, khusus CFO (grup rute /finance).
 */
class RabTrackerControllerTest extends TestCase
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
            'year' => 2026, 'division' => 'OPERATION', 'account_name' => 'Beban Tutor',
            'account_code' => '5001', 'rab_prev' => 280_000_000,
            'q1' => 60_000_000, 'q2' => 60_000_000, 'q3' => 60_000_000, 'q4' => 60_000_000,
        ]);
    }

    #[Test]
    public function cfo_can_view_the_tracker_with_budget_and_monthly_actuals(): void
    {
        $this->seedRab();
        RabMonthlyActual::create(['year' => 2026, 'account_code' => '5001', 'month' => 1, 'amount' => 15_000_000]);
        RabMonthlyActual::create(['year' => 2026, 'account_code' => '5001', 'month' => 2, 'amount' => 18_000_000]);

        $res = $this->actingAs($this->cfo)
            ->get(route('finance.rab-tracker.index', ['year' => 2026]))
            ->assertOk()
            ->assertViewIs('admin.rab-tracker.index')
            ->assertSee('Beban Tutor');

        $row = collect($res->viewData('rows'))->firstWhere('account_code', '5001');
        $this->assertSame(240_000_000, $row['budget_total']);
        $this->assertSame(33_000_000, $row['real_total']);   // 15jt + 18jt
        $this->assertSame(15_000_000, $row['months'][1]);
        $this->assertSame(280_000_000, $row['rab_prev']);
        $this->assertSame(240_000_000, $res->viewData('totals')['budget_total']);
    }

    #[Test]
    public function cfo_can_save_monthly_actuals_and_they_persist(): void
    {
        $this->seedRab();

        $this->actingAs($this->cfo)
            ->postJson(route('finance.rab-tracker.actuals'), [
                'year' => 2026,
                'actuals' => [
                    ['account_code' => '5001', 'month' => 3, 'amount' => 12_500_000],
                    ['account_code' => '__REVENUE__', 'month' => 3, 'amount' => 90_000_000],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('rab_monthly_actuals', [
            'year' => 2026, 'account_code' => '5001', 'month' => 3, 'amount' => 12_500_000,
        ]);
        $this->assertDatabaseHas('rab_monthly_actuals', [
            'year' => 2026, 'account_code' => '__REVENUE__', 'month' => 3, 'amount' => 90_000_000,
        ]);
    }

    #[Test]
    public function saving_the_same_month_twice_updates_instead_of_duplicating(): void
    {
        $this->seedRab();
        $save = fn ($amount) => $this->actingAs($this->cfo)->postJson(route('finance.rab-tracker.actuals'), [
            'year' => 2026,
            'actuals' => [['account_code' => '5001', 'month' => 4, 'amount' => $amount]],
        ]);

        $save(1_000_000)->assertOk();
        $save(2_000_000)->assertOk();

        $this->assertSame(1, RabMonthlyActual::where('account_code', '5001')->where('month', 4)->count());
        $this->assertSame(2_000_000, (int) RabMonthlyActual::where('account_code', '5001')->where('month', 4)->value('amount'));
    }

    #[Test]
    public function admin_and_tutor_cannot_access_the_tracker(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('finance.rab-tracker.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'tutor']))
            ->post(route('finance.rab-tracker.actuals'), ['year' => 2026, 'actuals' => []])->assertForbidden();
    }
}
