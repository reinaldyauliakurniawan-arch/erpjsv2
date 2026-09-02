<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\EnrollmentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        // Akun wajib — kode harus cocok dengan App\Enums\AccountCode
        foreach ([
            '1001' => 'Kas di tangan',
            '1002' => 'Kas di Bank',
            '1003' => 'Piutang Customer',
            '2002' => 'Pendapatan Diterima Dimuka',
            '4101' => 'Pendapatan B2B (Class)',
        ] as $code => $name) {
            Account::factory()->create(['code' => $code, 'name' => $name]);
        }
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
        $program = Program::factory()->create(['type' => 'private', 'price' => 1_500_000, 'total_meetings' => 8, 'min_quota' => 1]);
        $classroom = Classroom::factory()->create(['capacity' => 5]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.store'), [
                'program_id' => $program->id,
                'enrollment_date' => '2025-01-10',
                'expiry_date' => '2025-06-10',
                'payment_method' => 'full upfront',
                'payment_channel' => 'bank',
                'total_amount' => 1_500_000,
                'new_student' => [
                    'name' => 'Citra Dewi',
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
        $enrollment = Enrollment::factory()->create(['payment_status' => PaymentStatus::PENDING->value]);
        $installment = Installment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'amount' => 750_000,
            'paid_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId' => $enrollment->id,
                'installmentId' => $installment->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($installment->fresh()->paid_at);
    }

    #[Test]
    public function marking_already_paid_installment_returns_error()
    {
        $enrollment = Enrollment::factory()->create();
        $installment = Installment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId' => $enrollment->id,
                'installmentId' => $installment->id,
            ]))
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function payment_status_becomes_full_when_all_installments_paid()
    {
        $enrollment = Enrollment::factory()->create(['payment_status' => PaymentStatus::PARTIAL->value]);
        $inst1 = Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => now()]);
        $inst2 = Installment::factory()->create(['enrollment_id' => $enrollment->id, 'paid_at' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.installments.paid', [
                'enrollmentId' => $enrollment->id,
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
                'enrollmentId' => $enrollment->id,
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
        $program = Program::factory()->create(['total_meetings' => 8, 'price' => 800_000]);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'remaining_meetings' => 3,
            'total_amount' => 800_000,
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
        $program = Program::factory()->create(['total_meetings' => 8, 'price' => 800_000]);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'payment_method' => 'full upfront', // totalPaid = total_amount
            'remaining_meetings' => 4,
            'total_amount' => 800_000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.enrollments.expire', $enrollment->id));

        // 800rb dibayar, 0 pertemuan diakui -> seluruh Deferred Revenue (800rb)
        // diakui sebagai pendapatan saat expire (kebijakan hangus).
        $this->assertDatabaseHas('journals', [
            'reference' => "MANUAL-EXPIRY-{$enrollment->id}",
            'total_amount' => 800_000,
        ]);
    }

    // =========================================================
    //  GRADUATE
    // =========================================================

    #[Test]
    public function admin_can_graduate_active_enrollment_with_zero_remaining_meetings()
    {
        $enrollment = Enrollment::factory()->create([
            'status' => 'active',
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
            'status' => 'active',
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

    // =========================================================
    //  EDIT & UPDATE
    // =========================================================

    #[Test]
    public function admin_can_view_enrollment_edit_form()
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.edit', $enrollment))
            ->assertOk()
            ->assertViewIs('admin.enrollments.edit');
    }

    #[Test]
    public function admin_can_update_core_enrollment_fields()
    {
        $program = Program::factory()->create(['total_meetings' => 20]);
        $newProgram = Program::factory()->create(['total_meetings' => 6]);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id,
            'payment_method' => 'full upfront',
            'payment_status' => PaymentStatus::PARTIAL->value,
            'total_amount' => 3_600_000,
            'status' => 'active',
            'remaining_meetings' => 20,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $enrollment->student_id,
                'program_id' => $newProgram->id,
                'class_session_id' => '',
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-09-01',
                'payment_method' => 'full upfront',
                'payment_channel' => 'bank',
                'total_amount' => 1_350_000,
                'payment_status' => PaymentStatus::FULL->value,
                'status' => 'active',
                'remaining_meetings' => 4,
            ])
            ->assertRedirect(route('admin.enrollments.show', $enrollment->id))
            ->assertSessionHas('success');

        $enrollment->refresh();
        $this->assertEquals($newProgram->id, $enrollment->program_id);
        $this->assertEquals('1350000.00', $enrollment->total_amount);
        $this->assertEquals(PaymentStatus::FULL->value, $enrollment->payment_status);
        $this->assertEquals(4, $enrollment->remaining_meetings);
    }

    #[Test]
    public function update_reconciles_installment_rows()
    {
        $enrollment = Enrollment::factory()->create([
            'payment_method' => 'installment',
            'total_amount' => 3_000_000,
        ]);
        $keep = Installment::factory()->create([
            'enrollment_id' => $enrollment->id, 'amount' => 1_000_000, 'paid_at' => null,
        ]);
        $drop = Installment::factory()->create([
            'enrollment_id' => $enrollment->id, 'amount' => 2_000_000, 'paid_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $enrollment->student_id,
                'program_id' => $enrollment->program_id,
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-10-01',
                'payment_method' => 'installment',
                'payment_channel' => 'bank',
                'total_amount' => 3_000_000,
                'payment_status' => PaymentStatus::PARTIAL->value,
                'status' => 'active',
                'remaining_meetings' => 10,
                'installments' => [
                    ['id' => $keep->id, 'amount' => 1_500_000, 'due_date' => '2026-07-01', 'payment_channel' => 'bank', 'paid' => '1'],
                    ['amount' => 1_500_000, 'due_date' => '2026-08-01', 'payment_channel' => 'bank'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('installments', ['id' => $drop->id]);
        $this->assertEquals('1500000.00', $keep->fresh()->amount);
        $this->assertNotNull($keep->fresh()->paid_at);
        $this->assertEquals(2, $enrollment->fresh()->installments()->count());
    }

    #[Test]
    public function update_rejects_installments_not_matching_total()
    {
        $enrollment = Enrollment::factory()->create([
            'payment_method' => 'installment',
            'total_amount' => 3_000_000,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $enrollment->student_id,
                'program_id' => $enrollment->program_id,
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-10-01',
                'payment_method' => 'installment',
                'payment_channel' => 'bank',
                'total_amount' => 3_000_000,
                'payment_status' => PaymentStatus::PARTIAL->value,
                'status' => 'active',
                'remaining_meetings' => 10,
                'installments' => [
                    ['amount' => 1_000_000, 'due_date' => '2026-07-01', 'payment_channel' => 'bank'],
                ],
            ])
            ->assertSessionHasErrors('installments');
    }

    #[Test]
    public function update_posts_adjusting_journal_when_rate_changes_after_revenue_recognized()
    {
        // Full-upfront 3.6jt, 20 meeting (rate 180rb), 1 pertemuan sudah diakui.
        [, $enrollment] = $this->enrollmentWithRecognizedMeeting(20, 3_600_000);
        // Program dikoreksi ke 10 meeting -> rate jadi 360rb -> revenue yang
        // wajib diakui utk 1 pertemuan naik 180rb -> butuh jurnal catch-up.
        $newProgram = Program::factory()->create(['total_meetings' => 10]);

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $enrollment->student_id,
                'program_id' => $newProgram->id,
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-10-01',
                'payment_method' => 'full upfront',
                'payment_channel' => 'bank',
                'total_amount' => 3_600_000,
                'status' => 'active',
                'remaining_meetings' => 9,
            ])
            ->assertRedirect(route('admin.enrollments.show', $enrollment->id))
            ->assertSessionHas('success');

        $this->assertEquals($newProgram->id, $enrollment->fresh()->program_id);
        $this->assertDatabaseHas('journals', ['reference' => "ENR-SYNC-{$enrollment->id}-1", 'enrollment_id' => $enrollment->id]);
        $ledger = app(EnrollmentLedgerService::class);
        $this->assertTrue($ledger->isInSync($enrollment->fresh()->load('program')));
    }

    #[Test]
    public function update_blocks_lowering_amount_below_cash_already_received()
    {
        // Kas 3.6jt sudah masuk; turunkan total ke 3jt = kelebihan bayar 600rb.
        [, $enrollment] = $this->enrollmentWithRecognizedMeeting(20, 3_600_000);

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $enrollment->student_id,
                'program_id' => $enrollment->program_id,
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-10-01',
                'payment_method' => 'full upfront',
                'payment_channel' => 'bank',
                'total_amount' => 3_000_000,
                'status' => 'active',
                'remaining_meetings' => 19,
            ])
            ->assertSessionHasErrors('error');

        $this->assertEquals('3600000.00', $enrollment->fresh()->total_amount);
    }

    #[Test]
    public function update_blocks_student_reassignment_once_revenue_recognized()
    {
        [$program, $enrollment] = $this->enrollmentWithRecognizedMeeting(20, 3_600_000);
        $other = Student::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.enrollments.update', $enrollment), [
                'student_id' => $other->id,
                'program_id' => $enrollment->program_id,
                'enrollment_date' => '2026-07-01',
                'expiry_date' => '2026-10-01',
                'payment_method' => 'full upfront',
                'payment_channel' => 'bank',
                'total_amount' => 3_600_000,
                'status' => 'active',
                'remaining_meetings' => 19,
            ])
            ->assertSessionHasErrors('error');

        $this->assertEquals($enrollment->student_id, $enrollment->fresh()->student_id);
    }

    /** @return array{0: Program, 1: Enrollment} */
    private function enrollmentWithRecognizedMeeting(int $meetings, int $total): array
    {
        $program = Program::factory()->create(['total_meetings' => $meetings]);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id,
            'payment_method' => 'full upfront',
            'total_amount' => $total,
            'payment_status' => PaymentStatus::FULL->value,
            'status' => 'active',
        ]);
        // Kas masuk penuh (biar buku besar sinkron di titik awal).
        app(AccountingService::class)->createJournal(
            '2026-07-01', "Pembayaran migrasi enrollment #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}",
            [
                ['account_code' => '1002', 'debit' => $total, 'credit' => 0],
                ['account_code' => '2002', 'debit' => 0, 'credit' => $total],
            ],
            'payment', $program->id, $enrollment->id,
        );
        // 1 pertemuan yang revenue-nya sudah diakui + jurnalnya.
        $classroom = Classroom::factory()->create();
        $attendanceId = DB::table('attendance')->insertGetId([
            'date' => '2026-07-05', 'time_block' => '18:30-20:00', 'classroom_id' => $classroom->id,
            'marked_by' => $this->admin->id, 'status' => 'finished', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance_student')->insert([
            'enrollment_id' => $enrollment->id, 'attendance_id' => $attendanceId,
            'is_present' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $perMeeting = (int) round($total / $meetings);
        app(AccountingService::class)->createJournal(
            '2026-07-05', "Revenue recognition enrollment #{$enrollment->id}", "REV-REC-{$attendanceId}-{$enrollment->id}",
            [
                ['account_code' => '2002', 'debit' => $perMeeting, 'credit' => 0],
                ['account_code' => '4101', 'debit' => 0, 'credit' => $perMeeting],
            ],
            'revenue_recognition', $program->id, $enrollment->id,
        );

        return [$program, $enrollment];
    }
}
