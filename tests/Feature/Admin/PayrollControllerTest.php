<?php

namespace Tests\Feature\Admin;

use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();

        // Payroll ada di area /finance — hanya untuk CFO.
        $this->cfo = User::factory()->create(['role' => 'cfo']);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    #[Test]
    public function cfo_can_view_payroll_index()
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.payroll.index'))
            ->assertOk()
            ->assertViewIs('admin.finance.payroll.index');
    }

    #[Test]
    public function cfo_can_run_payroll()
    {
        $this->actingAs($this->cfo)
            ->post(route('finance.payroll.store'), [
                'month' => '2025-01-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            PayrollRun::whereDate('month', '2025-01-01')->exists(),
            'Payroll run untuk Januari 2025 harus tercatat.'
        );
    }

    #[Test]
    public function running_payroll_twice_for_same_month_is_rejected()
    {
        PayrollRun::factory()->create(['month' => '2025-01-01', 'status' => 'pending']);

        $this->actingAs($this->cfo)
            ->post(route('finance.payroll.store'), ['month' => '2025-01-15'])
            ->assertSessionHasErrors('month');
    }

    #[Test]
    public function cfo_can_approve_payroll()
    {
        $payroll = PayrollRun::factory()->create(['status' => 'pending']);

        $this->actingAs($this->cfo)
            ->post(route('finance.payroll.approve', $payroll->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('approved', $payroll->fresh()->status);
    }

    #[Test]
    public function cannot_approve_already_approved_payroll()
    {
        $payroll = PayrollRun::factory()->create(['status' => 'approved']);

        $this->actingAs($this->cfo)
            ->post(route('finance.payroll.approve', $payroll->id))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function guest_cannot_access_payroll()
    {
        $this->get(route('finance.payroll.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_cannot_access_finance_payroll()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('finance.payroll.index'))
            ->assertForbidden();
    }
}
