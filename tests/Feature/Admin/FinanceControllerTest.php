<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Account;
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
