<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\FixedAsset;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AttendanceService;
use App\Services\DepreciationService;
use App\Services\EnrollmentLedgerService;
use App\Services\PayrollService;
use App\Services\TutorAssignmentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit integrasi antar-peran: aksi di satu peran harus menimbulkan efek yang
 * benar di peran lain (tutor, keuangan) — dan buku besar tidak boleh melenceng.
 */
class RoleDominoAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function assertBalanced(): void
    {
        $d = (float) DB::table('journal_items')->selectRaw('SUM(debit)-SUM(credit) d')->value('d');
        $this->assertEqualsWithDelta(0, $d, 0.01, 'Trial balance harus 0');
    }

    /** @return array{0:Enrollment,1:User,2:Tutor} */
    private function activeEnrollmentWithTutor(int $meetings, int $total, string $method = 'full upfront'): array
    {
        $program = Program::factory()->create(['total_meetings' => $meetings, 'price' => $total, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorUser->id]);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 100_000]);
        Schedule::factory()->create([
            'class_session_id' => $cs->id, 'classroom_id' => Classroom::factory()->create()->id,
            'day' => 'Senin', 'time_block' => '08:00-09:30',
        ]);

        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id,
            'payment_method' => $method, 'total_amount' => $total,
            'payment_status' => $method === 'full upfront' ? 'full' : 'partial',
            'status' => 'active', 'remaining_meetings' => $meetings,
            'enrollment_date' => '2026-01-05', 'expiry_date' => '2026-02-05',
        ]);
        DB::table('enrollment_tutor')->insert([
            'enrollment_id' => $enrollment->id, 'tutor_id' => $tutor->id,
            'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$enrollment, $tutorUser, $tutor];
    }

    private function mark(Enrollment $e, User $tutorUser, string $date): Attendance
    {
        return app(AttendanceService::class)->markAttendance([
            'class_session_id' => $e->class_session_id,
            'date' => $date, 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $e->id, 'is_present' => true]],
        ]);
    }

    // ── A1: kedaluwarsa otomatis ────────────────────────────────────────────

    #[Test]
    public function scheduled_expiry_keeps_ledger_in_sync_for_underpaid_installment(): void
    {
        // Kontrak 2jt / 10 pertemuan. Baru bayar DP 200rb, sudah 3 pertemuan
        // jalan (revenue diakui 600rb > kas 200rb -> ada piutang 400rb).
        [$enrollment, $tutorUser] = $this->activeEnrollmentWithTutor(10, 2_000_000, 'installment');
        Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 200_000, 'due_date' => '2026-01-05', 'paid_at' => '2026-01-05', 'payment_channel' => 'bank']);
        Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 1_800_000, 'due_date' => '2026-02-01', 'payment_channel' => 'bank']);
        app(AccountingService::class)->createJournal('2026-01-05', "DP #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}", [
            ['account_code' => '1002', 'debit' => 200_000, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => 200_000],
        ], 'payment', $enrollment->program_id, $enrollment->id);

        $this->mark($enrollment, $tutorUser, '2026-01-06');
        $this->mark($enrollment, $tutorUser, '2026-01-13');
        $this->mark($enrollment, $tutorUser, '2026-01-20');

        $enrollment->update(['expiry_date' => now()->subDay()->toDateString()]);
        $this->artisan('app:check-expirations')->assertOk();

        $fresh = $enrollment->fresh()->load('program');
        $this->assertSame('expired', $fresh->status);
        $this->assertSame(0, $fresh->remaining_meetings);

        // Buku besar sinkron & seimbang; Deferred Revenue tidak minus.
        $this->assertTrue(app(EnrollmentLedgerService::class)->isInSync($fresh));
        $this->assertBalanced();
        $deferred = (float) DB::table('journal_items as ji')->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('a.code', '2002')->selectRaw('SUM(ji.credit)-SUM(ji.debit) v')->value('v');
        $this->assertGreaterThanOrEqual(0, round($deferred, 2), 'Deferred revenue tidak boleh minus');

        // Jurnal enrollment tertaut (bukan enrollment_id NULL).
        $this->assertDatabaseHas('journals', ['enrollment_id' => $fresh->id, 'type' => 'revenue_recognition']);
    }

    // ── B1: keluarkan siswa dari kelas -> jam tutor bebas ───────────────────

    #[Test]
    public function removing_only_student_from_class_frees_the_tutor_hour(): void
    {
        [$enrollment, , $tutor] = $this->activeEnrollmentWithTutor(10, 2_000_000);
        app(TutorAssignmentService::class)->recomputeAvailability($tutor->id);
        $this->assertDatabaseHas('tutor_availability', [
            'tutor_id' => $tutor->id, 'day' => 'Senin', 'time_block' => '08:00-09:30', 'status' => 'occupied',
        ]);

        $this->actingAs($this->admin)->post(route('admin.class-sessions.remove', $enrollment->class_session_id), [
            'enrollment_id' => $enrollment->id,
        ])->assertRedirect();

        $slot = TutorAvailability::where('tutor_id', $tutor->id)->where('time_block', '08:00-09:30')->first();
        $this->assertNotSame('occupied', $slot?->status, 'Slot jam tutor harus bebas lagi');
        $this->assertDatabaseMissing('enrollment_tutor', ['enrollment_id' => $enrollment->id, 'tutor_id' => $tutor->id]);
    }

    // ── B4: hapus 1 siswa kelas grup TIDAK menghapus jadwal kelas ───────────

    #[Test]
    public function deleting_one_group_student_keeps_the_class_schedule(): void
    {
        $program = Program::factory()->create(['type' => 'group', 'total_meetings' => 10, 'min_quota' => 1, 'price' => 1_000_000]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $schedule = Schedule::factory()->create([
            'class_session_id' => $cs->id, 'classroom_id' => Classroom::factory()->create()->id,
            'day' => 'Rabu', 'time_block' => '10:00-11:30',
        ]);
        $a = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        $b = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);

        $this->actingAs($this->admin)->deleteJson(route('admin.enrollments.destroy', $a))->assertOk();

        $this->assertDatabaseMissing('enrollments', ['id' => $a->id]);
        $this->assertDatabaseHas('enrollments', ['id' => $b->id]);
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'class_session_id' => $cs->id]);
    }

    // ── A3: CFO reverse jurnal -> data operasional ikut kembali ─────────────

    #[Test]
    public function cfo_reversing_revenue_recognition_journal_reverses_whole_meeting(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        [$enrollment, $tutorUser] = $this->activeEnrollmentWithTutor(10, 2_000_000);
        app(AccountingService::class)->createJournal('2026-01-05', "DP #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}", [
            ['account_code' => '1002', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => 2_000_000],
        ], 'payment', $enrollment->program_id, $enrollment->id);
        $att = $this->mark($enrollment, $tutorUser, '2026-01-06');
        $this->assertSame(9, $enrollment->fresh()->remaining_meetings);

        $revRec = Journal::where('reference', "REV-REC-{$att->id}-{$enrollment->id}")->firstOrFail();
        $this->actingAs($cfo)->post(route('finance.journals.reverse', $revRec))->assertRedirect();

        $this->assertSame(10, $enrollment->fresh()->remaining_meetings, 'sisa pertemuan kembali');
        $this->assertSoftDeleted('attendance', ['id' => $att->id]);
        $this->assertBalanced();
    }

    #[Test]
    public function cfo_reversing_installment_journal_marks_it_unpaid_and_rebuilds_ledger(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        [$enrollment] = $this->activeEnrollmentWithTutor(10, 2_000_000, 'installment');
        $inst = Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 500_000, 'due_date' => '2026-01-10', 'payment_channel' => 'bank']);
        Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 1_500_000, 'due_date' => '2026-02-10', 'payment_channel' => 'bank']);

        $this->actingAs($this->admin)->post(route('admin.enrollments.installments.paid', [$enrollment->id, $inst->id]))->assertRedirect();
        $this->assertNotNull($inst->fresh()->paid_at);

        $journal = Journal::where('reference', "INSTALLMENT-{$inst->id}")->firstOrFail();
        $this->actingAs($cfo)->post(route('finance.journals.reverse', $journal))->assertRedirect();

        $this->assertNull($inst->fresh()->paid_at, 'cicilan kembali belum lunas');
        $this->assertSame('partial', $enrollment->fresh()->payment_status);
        $this->assertTrue(app(EnrollmentLedgerService::class)->isInSync($enrollment->fresh()->load('program')));
        $this->assertBalanced();
    }

    #[Test]
    public function cfo_cannot_plain_reverse_an_enrollment_payment_journal(): void
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        [$enrollment] = $this->activeEnrollmentWithTutor(10, 2_000_000);
        $j = app(AccountingService::class)->createJournal('2026-01-05', "DP #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}", [
            ['account_code' => '1002', 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => '2002', 'debit' => 0, 'credit' => 2_000_000],
        ], 'payment', $enrollment->program_id, $enrollment->id);

        $this->actingAs($cfo)->post(route('finance.journals.reverse', $j))->assertRedirect();
        $this->assertDatabaseMissing('journals', ['reference' => "REV-PAYMENT-ENROLL-{$enrollment->id}"]);
    }

    // ── A2: ganti peran user diblokir kalau ada riwayat keuangan ────────────

    #[Test]
    public function changing_student_role_is_blocked_when_they_have_payment_journals(): void
    {
        $student = Student::factory()->create();
        $student->user->forceFill(['role' => 'student'])->save();
        $enr = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'expired']);
        Journal::factory()->create(['reference' => "PAYMENT-ENROLL-{$enr->id}", 'enrollment_id' => $enr->id]);

        $this->actingAs($this->admin)->patch(route('admin.settings.users.update', $student->user), [
            'name' => $student->user->name, 'email' => $student->user->email, 'role' => 'tutor',
        ])->assertSessionHas('error');

        $this->assertSame('student', $student->user->fresh()->role);
        $this->assertDatabaseHas('enrollments', ['id' => $enr->id]);
    }

    // ── C1: honor telat -> payroll susulan, gaji tidak dobel ────────────────

    #[Test]
    public function supplementary_payroll_run_pays_late_fee_without_repaying_salary(): void
    {
        [$enrollment, $tutorUser, $tutor] = $this->activeEnrollmentWithTutor(10, 2_000_000);
        $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 3_000_000, 'salaried_since' => '2026-01-01']);

        $svc = app(PayrollService::class);
        $run1 = $svc->createPayrollRun('2026-01');
        $svc->approvePayrollRun($run1->id, $this->admin->id);
        $salaryJournals = Journal::where('reference', "PAYROLL-{$run1->id}-TUTOR-{$tutor->id}-SALARY")->count();
        $this->assertSame(1, $salaryJournals);

        // Honor freelance telat masuk untuk Januari (tutor sempat freelance dulu).
        $tutor->update(['salaried_since' => '2026-02-01']);
        $att = $this->mark($enrollment, $tutorUser, '2026-01-20');
        $tutor->update(['salaried_since' => '2026-01-01']);

        $this->assertNull(DB::table('attendance_tutor')->where('attendance_id', $att->id)->value('paid_at'));

        $run2 = $svc->createPayrollRun('2026-01');
        $svc->approvePayrollRun($run2->id, $this->admin->id);

        // Gaji tidak dibayar lagi oleh run kedua.
        $this->assertSame(0, Journal::where('reference', "PAYROLL-{$run2->id}-TUTOR-{$tutor->id}-SALARY")->count());
        // Honor per-pertemuan yang telat kini terbayar & tertaut ke run kedua.
        $paidRow = DB::table('attendance_tutor')->where('attendance_id', $att->id)->first();
        $this->assertNotNull($paidRow->paid_at);
        $this->assertSame($run2->id, $paidRow->payroll_run_id);
        $this->assertBalanced();
    }

    // ── C2: tutor tetap tanggal mundur ditolak ─────────────────────────────

    #[Test]
    public function making_tutor_permanent_is_rejected_when_backdated_past_a_freelance_meeting(): void
    {
        [$enrollment, $tutorUser, $tutor] = $this->activeEnrollmentWithTutor(10, 2_000_000);
        $this->mark($enrollment, $tutorUser, '2026-01-20'); // freelance, honor accrued

        $this->actingAs($this->admin)->put(route('admin.tutors.update', $tutor), [
            'name' => $tutorUser->name, 'email' => $tutorUser->email, 'persona' => 'x',
            'status' => 'active', 'employment_type' => 'permanent',
            'monthly_salary' => 3_000_000, 'salaried_since' => '2026-01-01',
        ])->assertSessionHasErrors('salaried_since');

        $this->assertSame('freelance', $tutor->fresh()->employment_type);
    }

    // ── D1: koreksi aset -> penyusutan dibangun ulang ──────────────────────

    #[Test]
    public function correcting_a_fixed_asset_rebuilds_its_depreciation_journals(): void
    {
        $exp = Account::where('code', '5001')->value('id') ?? Account::factory()->create(['code' => '5199', 'type' => 'Expense'])->id;
        $acc = Account::factory()->create(['code' => '1599', 'type' => 'Asset', 'name' => 'Akm. Penyusutan'])->id;
        $asset = FixedAsset::create([
            'name' => 'Laptop', 'category' => 'Elektronik', 'acquired_at' => now()->subMonths(3)->toDateString(),
            'cost' => 12_000_000, 'salvage_value' => 0, 'useful_life' => 24, 'depreciation_method' => 'straight_line',
            'expense_account_id' => $exp, 'accumulated_account_id' => $acc, 'is_active' => true,
        ]);
        app(DepreciationService::class)->rebuildAsset($asset);
        $before = Journal::where('type', 'adjusting')->where('description', 'like', '%Laptop%')->sum('total_amount');
        $this->assertGreaterThan(0, $before);

        // Harga dikoreksi ke setengahnya.
        $this->actingAs(User::factory()->create(['role' => 'cfo']))
            ->patch(route('finance.assets.update', $asset), [
                'name' => 'Laptop', 'category' => 'Elektronik', 'acquired_at' => $asset->acquired_at->toDateString(),
                'cost' => 6_000_000, 'salvage_value' => 0, 'useful_life' => 24, 'depreciation_method' => 'straight_line',
                'expense_account_id' => $exp, 'accumulated_account_id' => $acc, 'is_active' => 1,
            ])->assertRedirect();

        $after = Journal::where('type', 'adjusting')->where('description', 'like', '%Laptop%')->sum('total_amount');
        $this->assertEqualsWithDelta($before / 2, $after, 1, 'Total penyusutan harus jadi separuh');
        $this->assertBalanced();
    }

    // ── D2: kuota program diturunkan -> waitlist aktif ─────────────────────

    #[Test]
    public function lowering_program_min_quota_activates_waiting_students(): void
    {
        $program = Program::factory()->create(['total_meetings' => 10, 'min_quota' => 5, 'price' => 1_000_000]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'inactive']);
        $tutor = Tutor::factory()->create();
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        $e1 = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'waitlist']);
        $e2 = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'waitlist']);

        $this->actingAs($this->admin)->put(route('admin.programs.update', $program), [
            'name' => $program->name, 'type' => $program->type, 'price' => $program->price,
            'total_meetings' => $program->total_meetings, 'min_quota' => 2,
        ])->assertRedirect();

        $this->assertSame('active', $e1->fresh()->status);
        $this->assertSame('active', $e2->fresh()->status);
    }
}
