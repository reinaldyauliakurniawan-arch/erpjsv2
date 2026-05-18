<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        // Akun wajib
        Account::factory()->create(['code' => '1101', 'name' => 'Cash/Bank']);
        Account::factory()->create(['code' => '2201', 'name' => 'Deferred Revenue']);
        Account::factory()->create(['code' => '4101', 'name' => 'Tuition Fees Revenue']);
    }

    // =========================================================
    //  INDEX
    // =========================================================

    #[Test]
    public function admin_can_view_enrollment_list()
    {
        Enrollment::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertViewIs('admin.enrollments.index');
    }

    #[Test]
    public function guest_cannot_access_enrollment_list()
    {
        $this->get(route('admin.enrollments.index'))
            ->assertRedirect(route('login'));
    }

    // =========================================================
    //  CREATE & STORE
    // =========================================================

    #[Test]
    public function admin_can_view_enrollment_create_form()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.create'))
            ->assertOk()
            ->assertViewIs('admin.enrollments.create');
    }

    #[Test]
    public function admin_can_store_enrollment_with_full_upfront_payment()
    {
        $program   = Program::factory()->create(['type' => 'private', 'price' => 1_500_000, 'total_meetings' => 8, 'min_quota' => 1]);
        $classroom = Classroom::factory()->create(['capacity' => 5]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.store'), [
                'program_id'      => $program->id,
                'enrollment_date' => '2025-01-10',
                'expiry_date'     => '2025-06-10',
                'payment_method'  => 'full upfront',
                'new_student'     => [
                    'name'  => 'Citra Dewi',
                    'email' => 'citra@example.com',
                ],
                'schedules' => [
                    ['classroom_id' => $classroom->id, 'day' => 'Monday', 'time_block' => '08:00-09:30'],
                ],
            ])
            ->assertRedirect(route('admin.enrollments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'citra@example.com']);
        $this->assertDatabaseHas('enrollments', ['status' => 'active']);
    }

    #[Test]
    public function store_enrollment_fails_with_missing_required_fields()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.store'), [])
            ->assertSessionHasErrors();
    }

    // =========================================================
    //  SHOW
    // =========================================================

    #[Test]
    public function admin_can_view_enrollment_detail()
    {
        $enrollment = Enrollment::factory()->withRelations()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.show', $enrollment))
            ->assertOk()
            ->assertViewIs('admin.enrollments.show');
    }

    #[Test]
    public function show_returns_404_for_nonexistent_enrollment()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.show', 99999))
            ->assertNotFound();
    }

    // =========================================================
    //  MARK INSTALLMENT PAID
    // =========================================================

    #[Test]
    public function admin_can_mark_installment_as_paid()
    {
        $enrollment  = Enrollment::factory()->create(['payment_status' => PaymentStatus::PENDING->value]);
        $installment = Installment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'amount'        => 750_000,
            'paid_at'       => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId'  => $enrollment->id,
                'installmentId' => $installment->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($installment->fresh()->paid_at);
    }

    #[Test]
    public function marking_already_paid_installment_returns_error()
    {
        $enrollment  = Enrollment::factory()->create();
        $installment = Installment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'paid_at'       => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId'  => $enrollment->id,
                'installmentId' => $installment->id,
            ]))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function payment_status_becomes_full_when_all_installments_paid()
    {
        $enrollment = Enrollment::factory()->create(['payment_status' => PaymentStatus::PARTIAL->value]);
        $inst1      = Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => now()]);
        $inst2      = Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId'  => $enrollment->id,
                'installmentId' => $inst2->id,
            ]));

        $this->assertEquals(PaymentStatus::FULL->value, $enrollment->fresh()->payment_status);
    }

    #[Test]
    public function payment_status_stays_partial_when_some_installments_remain()
    {
        $enrollment = Enrollment::factory()->create(['payment_status' => PaymentStatus::PENDING->value]);
        Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => null]); // belum bayar
        $paying = Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId'  => $enrollment->id,
                'installmentId' => $paying->id,
            ]));

        $this->assertEquals(PaymentStatus::PARTIAL->value, $enrollment->fresh()->payment_status);
    }

    // =========================================================
    //  EXPIRE
    // =========================================================

    #[Test]
    public function admin_can_expire_active_enrollment()
    {
        $program    = Program::factory()->create(['total_meetings' => 8, 'price' => 800_000]);
        $enrollment = Enrollment::factory()->create([
            'program_id'         => $program->id,
            'status'             => 'active',
            'remaining_meetings' => 3,
            'total_amount'       => 800_000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.expire', $enrollment->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('expired', $enrollment->fresh()->status);
        $this->assertEquals(0, $enrollment->fresh()->remaining_meetings);
    }

    #[Test]
    public function expiring_inactive_enrollment_returns_error()
    {
        $enrollment = Enrollment::factory()->create(['status' => 'graduate']);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.expire', $enrollment->id))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function expire_creates_journal_to_recognize_remaining_revenue()
    {
        $program    = Program::factory()->create(['total_meetings' => 8, 'price' => 800_000]);
        $enrollment = Enrollment::factory()->create([
            'program_id'         => $program->id,
            'status'             => 'active',
            'remaining_meetings' => 4, // 4 * (800000/8) = 400_000
            'total_amount'       => 800_000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.expire', $enrollment->id));

        $this->assertDatabaseHas('journals', ['total_amount' => 400_000]);
    }

    // =========================================================
    //  GRADUATE
    // =========================================================

    #[Test]
    public function admin_can_graduate_active_enrollment_with_zero_remaining_meetings()
    {
        $enrollment = Enrollment::factory()->create([
            'status'             => 'active',
            'remaining_meetings' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.graduate', $enrollment->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('graduate', $enrollment->fresh()->status);
    }

    #[Test]
    public function cannot_graduate_enrollment_with_remaining_meetings()
    {
        $enrollment = Enrollment::factory()->create([
            'status'             => 'active',
            'remaining_meetings' => 2,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.graduate', $enrollment->id))
            ->assertSessionHasErrors('error');

        $this->assertNotEquals('graduate', $enrollment->fresh()->status);
    }

    #[Test]
    public function cannot_graduate_inactive_enrollment()
    {
        $enrollment = Enrollment::factory()->create(['status' => 'expired']);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.graduate', $enrollment->id))
            ->assertSessionHasErrors('error');
    }

    // =========================================================
    //  ROLE GUARD
    // =========================================================

    #[Test]
    public function student_cannot_access_admin_enrollment_routes()
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.enrollments.index'))
            ->assertForbidden();
    }

    #[Test]
    public function tutor_cannot_access_admin_enrollment_routes()
    {
        $tutor = User::factory()->create(['role' => 'tutor']);

        $this->actingAs($tutor)
            ->get(route('admin.enrollments.index'))
            ->assertForbidden();
    }
}
