<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\PayrollRun;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);

        Account::factory()->create(['code' => '1101', 'name' => 'Cash/Bank']);
        Account::factory()->create(['code' => '5101', 'name' => 'Salary Expense']);
    }

    #[Test]
    public function admin_can_view_payroll_index()
    {
        $this->actingAs($this->admin)
            ->get(route('finance.payroll.index'))
            ->assertOk()
            ->assertViewIs('admin.payroll.index');
    }

    #[Test]
    public function admin_can_run_payroll()
    {
        $tutor = Tutor::factory()->withUser()->withRate()->create();

        $this->actingAs($this->admin)
            ->post(route('finance.payroll.store'), [
                'period_start' => '2025-01-01',
                'period_end'   => '2025-01-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_runs', ['period_start' => '2025-01-01']);
    }

    #[Test]
    public function admin_can_approve_payroll()
    {
        $payroll = PayrollRun::factory()->create(['status' => 'draft']);

        $this->actingAs($this->admin)
            ->post(route('finance.payroll.approve', $payroll->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('approved', $payroll->fresh()->status);
    }

    #[Test]
    public function cannot_approve_already_approved_payroll()
    {
        $payroll = PayrollRun::factory()->create(['status' => 'approved']);

        $this->actingAs($this->admin)
            ->post(route('finance.payroll.approve', $payroll->id))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function guest_cannot_access_payroll()
    {
        $this->get(route('finance.payroll.index'))
            ->assertRedirect(route('login'));
    }
}
