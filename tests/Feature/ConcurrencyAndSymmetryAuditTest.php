<?php

namespace Tests\Feature;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AttendanceService;
use App\Services\EnrollmentLedgerService;
use App\Services\EnrollmentService;
use App\Services\PayrollService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit lanjutan — 3 area:
 *  1. Race / over-booking: guard kapasitas kelas, ruang, & slot tutor di
 *     EnrollmentService benar-benar menolak (bukan cuma bergantung frontend).
 *     Catatan: sqlite :memory: tidak bisa concurrency asli — test ini menjamin
 *     CEK-nya ada & benar (regression guard). Lock baris induk dipasang supaya
 *     cek itu tidak bisa dilewati balapan di MySQL produksi.
 *  2. Simetri PayrollService: reverse benar-benar meng-undo approve — jurnal,
 *     paid_at, payroll_run_id — tanpa sisa; trial balance kembali 0.
 *  3. EnrollmentLedgerService::reconcile() lewat command sync — enrollment yang
 *     melenceng jadi sinkron & seimbang.
 */
class ConcurrencyAndSymmetryAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function tb(): float
    {
        return (float) DB::table('journal_items')->selectRaw('ROUND(SUM(debit)-SUM(credit),2) d')->value('d');
    }

    private function assertBalanced(): void
    {
        $this->assertEqualsWithDelta(0, $this->tb(), 0.01, 'Trial balance harus 0');
    }

    // ═══ AREA 1 — OVER-BOOKING GUARDS ═══════════════════════════════════════

    #[Test]
    public function group_class_at_capacity_rejects_a_new_direct_enrollment(): void
    {
        $room = Classroom::factory()->create(['capacity' => 2]);
        $program = Program::factory()->create(['type' => 'group', 'total_meetings' => 10, 'min_quota' => 1, 'price' => 800_000]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id, 'day' => 'Senin', 'time_block' => '08:00-09:30']);
        // Kelas sudah penuh (2/2).
        Enrollment::factory()->count(2)->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/penuh/i');

        app(EnrollmentService::class)->enroll([
            'program_id' => $program->id,
            'class_session_id' => $cs->id,
            'enrollment_date' => '2026-01-05', 'expiry_date' => '2026-06-05',
            'payment_method' => 'full upfront', 'payment_channel' => 'bank', 'total_amount' => 800_000,
            'new_student' => ['name' => 'Overflow', 'email' => 'of@x.test'],
            'schedules' => [['classroom_id' => $room->id, 'day' => 'Senin', 'time_block' => '08:00-09:30']],
        ]);
    }

    #[Test]
    public function assign_enrollment_to_a_full_class_is_rejected(): void
    {
        $room = Classroom::factory()->create(['capacity' => 1]);
        $program = Program::factory()->create(['type' => 'group', 'total_meetings' => 10, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id, 'day' => 'Rabu', 'time_block' => '10:00-11:30']);
        Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        $waiting = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => null, 'status' => 'active']);

        $this->actingAs($this->admin)
            ->post(route('admin.class-sessions.assign', $cs->id), ['enrollment_id' => $waiting->id])
            ->assertSessionHasErrors('error');

        $this->assertNull($waiting->fresh()->class_session_id);
    }

    #[Test]
    public function a_second_private_enrollment_cannot_take_a_room_already_booked_for_the_slot(): void
    {
        $room = Classroom::factory()->create(['capacity' => 5]); // fisik, capacity longgar
        $program = Program::factory()->create(['type' => 'private', 'total_meetings' => 8, 'min_quota' => 1, 'price' => 1_500_000]);

        $data = fn (string $email) => [
            'program_id' => $program->id,
            'enrollment_date' => '2026-01-05', 'expiry_date' => '2026-06-05',
            'payment_method' => 'full upfront', 'payment_channel' => 'bank', 'total_amount' => 1_500_000,
            'new_student' => ['name' => $email, 'email' => $email],
            'schedules' => [['classroom_id' => $room->id, 'day' => 'Kamis', 'time_block' => '13:00-14:30']],
        ];

        app(EnrollmentService::class)->enroll($data('p1@x.test'));

        $this->expectException(DomainException::class);
        app(EnrollmentService::class)->enroll($data('p2@x.test'));
    }

    #[Test]
    public function a_private_enrollment_is_rejected_when_the_room_already_hosts_a_group_class(): void
    {
        // Kapasitas ruang longgar (5) supaya cek PRA-transaksi lama (yang cuma
        // membandingkan jumlah jadwal vs kapasitas) LOLOS — jadi yang menolak di
        // sini adalah cek TERKUNCI di dalam transaction: "private butuh ruang sendiri".
        $room = Classroom::factory()->create(['capacity' => 5]);
        $groupProgram = Program::factory()->create(['type' => 'group', 'total_meetings' => 10, 'min_quota' => 1]);
        $groupCs = ClassSession::factory()->create(['program_id' => $groupProgram->id, 'status' => 'active']);
        Schedule::factory()->create(['class_session_id' => $groupCs->id, 'classroom_id' => $room->id, 'day' => 'Selasa', 'time_block' => '16:00-17:30']);
        Enrollment::factory()->create(['program_id' => $groupProgram->id, 'class_session_id' => $groupCs->id, 'status' => 'active']);

        $privateProgram = Program::factory()->create(['type' => 'private', 'total_meetings' => 8, 'min_quota' => 1, 'price' => 1_000_000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/ruang sendiri|dipakai kelas/i');

        app(EnrollmentService::class)->enroll([
            'program_id' => $privateProgram->id,
            'enrollment_date' => '2026-01-05', 'expiry_date' => '2026-06-05',
            'payment_method' => 'full upfront', 'payment_channel' => 'bank', 'total_amount' => 1_000_000,
            'new_student' => ['name' => 'Priv', 'email' => 'priv@x.test'],
            'schedules' => [['classroom_id' => $room->id, 'day' => 'Selasa', 'time_block' => '16:00-17:30']],
        ]);
    }

    #[Test]
    public function a_tutor_cannot_be_enrolled_into_two_classes_in_the_same_slot(): void
    {
        $tutor = Tutor::factory()->withUser()->create();
        $roomA = Classroom::factory()->create(['capacity' => 5]);
        $roomB = Classroom::factory()->create(['capacity' => 5]);
        $program = Program::factory()->create(['type' => 'private', 'total_meetings' => 8, 'min_quota' => 1, 'price' => 1_000_000]);

        $mk = fn (string $email, Classroom $room) => [
            'program_id' => $program->id,
            'enrollment_date' => '2026-01-05', 'expiry_date' => '2026-06-05',
            'payment_method' => 'full upfront', 'payment_channel' => 'bank', 'total_amount' => 1_000_000,
            'tutor_ids' => [$tutor->id],
            'new_student' => ['name' => $email, 'email' => $email],
            'schedules' => [['classroom_id' => $room->id, 'day' => 'Jumat', 'time_block' => '09:00-10:30']],
        ];

        app(EnrollmentService::class)->enroll($mk('a@x.test', $roomA));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/sudah mengajar kelas lain/i');
        app(EnrollmentService::class)->enroll($mk('b@x.test', $roomB));
    }

    // ═══ AREA 2 — PAYROLL APPROVE / REVERSE SYMMETRY ═══════════════════════

    /** @return array{0:Tutor,1:User,2:Enrollment} */
    private function tutorTeachingOneMeeting(string $month, float $rate = 120_000, bool $salaried = false): array
    {
        $program = Program::factory()->create(['total_meetings' => 10, 'price' => 2_000_000, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorUser->id]);
        if ($salaried) {
            $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 3_000_000, 'salaried_since' => '2020-01-01']);
        }
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => $rate]);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active',
            'payment_method' => 'full upfront', 'payment_status' => 'full', 'remaining_meetings' => 10,
        ]);
        app(AccountingService::class)->createJournal('2026-01-01', "DP #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}", [
            ['account_code' => AccountCode::BANK->value, 'debit' => 2_000_000, 'credit' => 0],
            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => 2_000_000],
        ], 'payment', $program->id, $enrollment->id);

        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $cs->id, 'date' => $month.'-15', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id, 'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);

        return [$tutor, $tutorUser, $enrollment];
    }

    #[Test]
    public function reverse_undoes_a_freelance_fee_payment_with_no_residue(): void
    {
        [$tutor] = $this->tutorTeachingOneMeeting('2026-01', 120_000);
        $svc = app(PayrollService::class);

        $run = $svc->createPayrollRun('2026-01');
        $svc->approvePayrollRun($run->id, $this->admin->id);

        $this->assertBalanced();
        $row = DB::table('attendance_tutor')->where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($row->paid_at);
        $this->assertSame($run->id, $row->payroll_run_id);
        $payableAfterApprove = $row->payable_amount;
        $this->assertDatabaseHas('journals', ['reference' => "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-PAY"]);

        $svc->reversePayrollRun($run->id, $this->admin->id);

        $this->assertBalanced();
        foreach (DB::table('journals')->where('reference', 'like', "PAYROLL-{$run->id}-%")->pluck('reference') as $ref) {
            $this->assertDatabaseHas('journals', ['reference' => "REV-{$ref}"]);
        }
        $row = DB::table('attendance_tutor')->where('tutor_id', $tutor->id)->first();
        $this->assertNull($row->paid_at);
        $this->assertNull($row->payroll_run_id);
        // payable_amount TIDAK berubah (itu akrual, bukan status bayar).
        $this->assertEquals($payableAfterApprove, $row->payable_amount);
        $this->assertSame('reversed', $run->fresh()->status);
    }

    #[Test]
    public function reverse_undoes_a_salary_payment_with_no_residue(): void
    {
        [$tutor] = $this->tutorTeachingOneMeeting('2026-06', 100_000, salaried: true);
        $svc = app(PayrollService::class);

        $run = $svc->createPayrollRun('2026-06');
        $svc->approvePayrollRun($run->id, $this->admin->id);
        $this->assertDatabaseHas('journals', ['reference' => "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY"]);
        $this->assertBalanced();

        $svc->reversePayrollRun($run->id, $this->admin->id);

        $this->assertDatabaseHas('journals', ['reference' => "REV-PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY"]);
        $this->assertBalanced();
        $this->assertSame('reversed', $run->fresh()->status);
    }

    #[Test]
    public function reversing_a_payroll_run_twice_is_rejected_cleanly(): void
    {
        $this->tutorTeachingOneMeeting('2026-02');
        $svc = app(PayrollService::class);
        $run = $svc->createPayrollRun('2026-02');
        $svc->approvePayrollRun($run->id, $this->admin->id);
        $svc->reversePayrollRun($run->id, $this->admin->id);

        $this->expectException(DomainException::class);
        $svc->reversePayrollRun($run->id, $this->admin->id);
    }

    #[Test]
    public function approving_a_payroll_run_twice_is_rejected_cleanly(): void
    {
        $this->tutorTeachingOneMeeting('2026-03');
        $svc = app(PayrollService::class);
        $run = $svc->createPayrollRun('2026-03');
        $svc->approvePayrollRun($run->id, $this->admin->id);

        $this->expectException(DomainException::class);
        $svc->approvePayrollRun($run->id, $this->admin->id);
    }

    #[Test]
    public function reversing_the_supplementary_run_leaves_the_original_runs_payments_intact(): void
    {
        // Dua pertemuan freelance bulan Mei; yang kedua tercatat setelah run A.
        [$tutor, $tutorUser, $enrollment] = $this->tutorTeachingOneMeeting('2026-05', 100_000);
        $svc = app(PayrollService::class);

        $runA = $svc->createPayrollRun('2026-05');
        $svc->approvePayrollRun($runA->id, $this->admin->id);
        $firstRow = DB::table('attendance_tutor')->where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($firstRow->paid_at);

        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $enrollment->class_session_id, 'date' => '2026-05-22', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id, 'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);
        $secondRow = DB::table('attendance_tutor')->where('tutor_id', $tutor->id)->whereNull('paid_at')->first();
        $this->assertNotNull($secondRow);

        $runB = $svc->createPayrollRun('2026-05');
        $svc->approvePayrollRun($runB->id, $this->admin->id);
        $this->assertSame($runB->id, DB::table('attendance_tutor')->where('id', $secondRow->id)->value('payroll_run_id'));

        $svc->reversePayrollRun($runB->id, $this->admin->id);

        // Baris pertemuan pertama (dibayar run A) TETAP terbayar & tertaut run A.
        $this->assertNotNull(DB::table('attendance_tutor')->where('id', $firstRow->id)->value('paid_at'));
        $this->assertSame($runA->id, DB::table('attendance_tutor')->where('id', $firstRow->id)->value('payroll_run_id'));
        // Baris pertemuan kedua (dibayar run B) kembali belum-bayar.
        $this->assertNull(DB::table('attendance_tutor')->where('id', $secondRow->id)->value('paid_at'));
        $this->assertNull(DB::table('attendance_tutor')->where('id', $secondRow->id)->value('payroll_run_id'));
        $this->assertBalanced();
    }

    // ═══ AREA 3 — reconcile() lewat command sync ═══════════════════════════

    #[Test]
    public function sync_ledger_command_reconciles_an_out_of_sync_enrollment(): void
    {
        $program = Program::factory()->create(['total_meetings' => 10, 'price' => 2_000_000, 'min_quota' => 1]);
        // Enrollment installment: 2 cicilan, satu lunas 800rb — TAPI belum ada
        // jurnal kas sama sekali (data "mentah" hasil impor tanpa proses).
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'status' => 'active', 'payment_method' => 'installment',
            'total_amount' => 2_000_000, 'payment_channel' => 'bank', 'payment_status' => 'partial',
            'enrollment_date' => '2026-01-05',
        ]);
        Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 800_000, 'due_date' => '2026-01-05', 'paid_at' => '2026-01-05', 'payment_channel' => 'bank']);
        Installment::create(['enrollment_id' => $enrollment->id, 'amount' => 1_200_000, 'due_date' => '2026-02-05', 'payment_channel' => 'bank']);

        $ledger = app(EnrollmentLedgerService::class);
        $this->assertFalse($ledger->isInSync($enrollment->load('program')));

        $this->artisan('enrollments:sync-ledger --apply')->assertOk();

        $this->assertTrue($ledger->isInSync($enrollment->fresh()->load('program')));
        $this->assertEqualsWithDelta(0, $this->tb(), 0.01);
        // Kas 800rb tercatat.
        $cash = (float) DB::table('journal_items as ji')->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->join('journals as j', 'j.id', '=', 'ji.journal_id')
            ->where('j.enrollment_id', $enrollment->id)
            ->whereIn('a.code', [AccountCode::CASH->value, AccountCode::BANK->value])
            ->selectRaw('SUM(ji.debit)-SUM(ji.credit) v')->value('v');
        $this->assertEqualsWithDelta(800_000, $cash, 1);
    }
}
