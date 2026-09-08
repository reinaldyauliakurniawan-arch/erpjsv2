<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Rab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RAB (edit anggaran) — khusus CFO.
 */
class RabControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cfo = User::factory()->create(['role' => 'cfo']);
        Account::factory()->create(['code' => '5001', 'name' => 'Beban Tutor', 'type' => 'Expense']);
        Account::factory()->create(['code' => '5002', 'name' => 'Beban Sewa', 'type' => 'Expense']);
    }

    #[Test]
    public function card_anggaran_tahunan_pakai_annual_budget_bukan_jumlah_kuartal(): void
    {
        Rab::create([
            'year' => 2026, 'division' => 'OPERATION', 'account_name' => 'Beban Tutor', 'account_code' => '5001',
            'annual_budget' => 245_000_000,
            'q1' => 60_000_000, 'q2' => 50_000_000, 'q3' => 60_000_000, 'q4' => 45_000_000, // Σ = 215jt
        ]);

        $res = $this->actingAs($this->cfo)->get(route('finance.rab.index', ['year' => 2026]))->assertOk();

        $this->assertSame(245_000_000, $res->viewData('totalBudget'));
    }

    #[Test]
    public function menyimpan_rab_tidak_menghapus_annual_budget_dan_rab_prev(): void
    {
        $this->actingAs($this->cfo)->postJson(route('finance.rab.store'), [
            'year' => 2026,
            'rows' => [[
                'division' => 'OPERATION', 'account_name' => 'Beban Tutor', 'account_code' => '5001',
                'activity' => null, 'rab_prev' => 200_000_000, 'annual_budget' => 245_000_000,
                'q1' => 60_000_000, 'q2' => 50_000_000, 'q3' => 60_000_000, 'q4' => 45_000_000,
            ]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('rabs', [
            'year' => 2026, 'account_code' => '5001',
            'rab_prev' => 200_000_000, 'annual_budget' => 245_000_000, 'total' => 215_000_000,
        ]);
    }

    #[Test]
    public function admin_dan_tutor_tidak_boleh_akses(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('finance.rab.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'tutor']))
            ->post(route('finance.rab.store'), ['year' => 2026, 'rows' => []])->assertForbidden();
    }
}
