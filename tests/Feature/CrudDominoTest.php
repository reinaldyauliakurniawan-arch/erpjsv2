<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AttendanceService;
use App\Services\EnrollmentLedgerService;
use App\Services\PayrollService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DOMINO CRUD — untuk tiap operasi tulis pada entitas inti, tegaskan:
 *   CREATE  -> apa/siapa yang kena dampak (jurnal, notifikasi, pivot, sisa)
 *   UPDATE  -> buku besar ikut menyesuaikan, trial balance tetap 0
 *   DELETE  -> cascade bersih, tidak ada yatim, trial balance tetap 0
 *   GAGAL   -> rollback penuh, tidak ada yang tersisa separuh
 */
class CrudDominoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function tb(): string
    {
        return (string) DB::table('journal_items')->selectRaw('ROUND(SUM(debit)-SUM(credit),2) d')->value('d') ?: '0';
    }

    private function assertBalanced(): void
    {
        $this->assertEqualsWithDelta(0, (float) $this->tb(), 0.01, 'Trial balance harus 0');
    }

    private function assertNoOrphanJournalItems(): void
    {
        $orphans = DB::table('journal_items as ji')
            ->leftJoin('journals as j', 'j.id', '=', 'ji.journal_id')
            ->whereNull('j.id')->count();
        $this->assertSame(0, $orphans, 'Tidak boleh ada journal_items yatim');
    }

    // ── CREATE ────────────────────────────────────────────────────────────

    #[Test]
    public function create_enrollment_ripples_to_ledger_pivots_and_notifies_class_tutors()
    {
        $program = Program::factory()->create(['type' => 'private', 'price' => 2_000_000, 'total_meetings' => 10, 'min_quota' => 1]);
        $classroom = Classroom::factory()->create(['capacity' => 5]);
        $tutor = Tutor::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.enrollments.store'), [
            'program_id' => $program->id,
            'enrollment_date' => '2026-07-01',
            'expiry_date' => '2026-12-01',
            'payment_method' => 'full upfront',
            'payment_channel' => 'bank',
            'total_amount' => 2_000_000,
            'tutor_ids' => [$tutor->id],
            'new_student' => ['name' => 'Budi', 'email' => 'budi@example.com'],
            'schedules' => [['classroom_id' => $classroom->id, 'day' => 'Senin', 'time_block' => '08:00-09:30']],
        ])->assertRedirect();

        $enrollment = Enrollment::firstWhere('program_id', $program->id);

        // jurnal DP: Dr Bank / Cr Deferred
        $this->assertDatabaseHas('journals', ['reference' => "PAYMENT-ENROLL-{$enrollment->id}", 'total_amount' => 2_000_000]);
        $this->assertBalanced();
        // tutor tersambung di KEDUA pivot
        $this->assertDatabaseHas('enrollment_tutor', ['enrollment_id' => $enrollment->id, 'tutor_id' => $tutor->id]);
        $this->assertDatabaseHas('class_session_tutor', ['tutor_id' => $tutor->id]);
        // tutor dapat notifikasi
        $this->assertGreaterThanOrEqual(1, $tutor->user->notifications()->count());
    }

    #[Test]
    public function create_attendance_ripples_to_students_finance_and_ledger()
    {
        [$enrollment, $tutorUser] = $this->activeEnrollmentWithTutor(10, 2_000_000, paidFull: true);

        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $enrollment->class_session_id,
            'date' => '2026-07-05', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);

        $fresh = $enrollment->fresh();
        $this->assertSame(9, $fresh->remaining_meetings, 'sisa pertemuan turun 1');
        $this->assertDatabaseHas('attendance_student', ['enrollment_id' => $enrollment->id, 'is_present' => true]);
        // revenue recognition + fee tutor terposting
        $this->assertDatabaseHas('journals', ['type' => 'revenue_recognition', 'enrollment_id' => $enrollment->id]);
        $this->assertDatabaseHas('journals', ['type' => 'tutor_accrual']);
        $this->assertBalanced();
        // siswa "melihat" lewat sub-ledger yang konsisten
        $this->assertTrue(app(EnrollmentLedgerService::class)->isInSync($fresh->load('program')));
    }

    // ── UPDATE ────────────────────────────────────────────────────────────

    #[Test]
    public function update_enrollment_amount_rebuilds_ledger_and_stays_balanced()
    {
        [$enrollment] = $this->activeEnrollmentWithTutor(10, 2_000_000, paidFull: true);

        $this->actingAs($this->admin)->put(route('admin.enrollments.update', $enrollment), [
            'student_id' => $enrollment->student_id,
            'program_id' => $enrollment->program_id,
            'enrollment_date' => '2026-07-01',
            'expiry_date' => '2026-12-01',
            'payment_method' => 'installment',
            'payment_channel' => 'bank',
            'total_amount' => 2_000_000,
            'status' => 'active',
            'remaining_meetings' => 10,
            'installments' => [
                ['amount' => 500_000, 'due_date' => '2026-07-01', 'payment_channel' => 'bank', 'paid' => true],
                ['amount' => 1_500_000, 'due_date' => '2026-08-01', 'payment_channel' => 'bank'],
            ],
        ])->assertRedirect();

        $fresh = $enrollment->fresh()->load('program');
        $this->assertSame('partial', $fresh->payment_status);
        $this->assertDatabaseHas('journals', ['reference' => "PAYMENT-ENROLL-{$enrollment->id}", 'total_amount' => 500_000]);
        $this->assertDatabaseMissing('journals', ['reference' => "ENR-SYNC-{$enrollment->id}-1"]);
        $this->assertTrue(app(EnrollmentLedgerService::class)->isInSync($fresh));
        $this->assertBalanced();
    }

    // ── DELETE ────────────────────────────────────────────────────────────

    #[Test]
    public function delete_enrollment_cascades_cleanly_and_stays_balanced()
    {
        [$enrollment, $tutorUser] = $this->activeEnrollmentWithTutor(10, 2_000_000, paidFull: true);
        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $enrollment->class_session_id,
            'date' => '2026-07-05', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);
        Installment::factory()->create(['enrollment_id' => $enrollment->id]);
        $csId = $enrollment->class_session_id;

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.enrollments.destroy', $enrollment))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseMissing('journals', ['enrollment_id' => $enrollment->id]);
        $this->assertDatabaseMissing('attendance_student', ['enrollment_id' => $enrollment->id]);
        $this->assertDatabaseMissing('installments', ['enrollment_id' => $enrollment->id]);
        // honor tutor (nempel ke sesi kelas) TIDAK ikut terhapus
        $this->assertDatabaseHas('class_session_tutor', ['class_session_id' => $csId]);
        $this->assertNoOrphanJournalItems();
        $this->assertBalanced();
    }

    #[Test]
    public function reversing_attendance_rolls_back_every_journal_and_counter()
    {
        [$enrollment, $tutorUser] = $this->activeEnrollmentWithTutor(10, 2_000_000, paidFull: true);
        $att = app(AttendanceService::class)->markAttendance([
            'class_session_id' => $enrollment->class_session_id,
            'date' => '2026-07-05', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);
        $this->assertSame(9, $enrollment->fresh()->remaining_meetings);

        app(AttendanceService::class)->reverseAttendance($att->fresh());

        $this->assertSame(10, $enrollment->fresh()->remaining_meetings, 'sisa pertemuan kembali');
        $this->assertSoftDeleted('attendance', ['id' => $att->id]);
        $this->assertDatabaseHas('journals', ['reference' => "REV-REV-REC-{$att->id}-{$enrollment->id}"]);
        $this->assertBalanced();
    }

    #[Test]
    public function payroll_approve_then_reverse_nets_to_zero()
    {
        [$enrollment, $tutorUser, $tutor] = $this->activeEnrollmentWithTutor(10, 2_000_000, paidFull: true, withReturnTutor: true);
        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $enrollment->class_session_id,
            'date' => '2026-07-05', 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);

        $svc = app(PayrollService::class);
        $run = $svc->createPayrollRun('2026-07');
        $svc->approvePayrollRun($run->id, $this->admin->id);
        $this->assertBalanced();
        $this->assertGreaterThanOrEqual(1, $tutor->user->notifications()->count());

        $svc->reversePayrollRun($run->id, $this->admin->id);
        $this->assertBalanced();
        $this->assertNull(DB::table('attendance_tutor')->where('tutor_id', $tutor->id)->value('paid_at'));
    }

    // ── GAGAL / ROLLBACK ─────────────────────────────────────────────────

    #[Test]
    public function a_failed_enrollment_store_leaves_nothing_behind()
    {
        $program = Program::factory()->create(['type' => 'private', 'total_meetings' => 10, 'min_quota' => 1]);

        // total_amount tidak cocok dg cicilan -> validasi gagal SEBELUM apa pun ditulis
        $this->actingAs($this->admin)->post(route('admin.enrollments.store'), [
            'program_id' => $program->id,
            'enrollment_date' => '2026-07-01', 'expiry_date' => '2026-12-01',
            'payment_method' => 'installment', 'payment_channel' => 'bank',
            'total_amount' => 2_000_000,
            'new_student' => ['name' => 'Gagal', 'email' => 'gagal@example.com'],
            'installments' => [['amount' => 100, 'due_date' => '2026-07-01']],
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', ['email' => 'gagal@example.com']);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('journals', 0);
    }

    // ── helper ───────────────────────────────────────────────────────────

    /**
     * @return array{0:Enrollment,1:User,2?:Tutor}
     */
    private function activeEnrollmentWithTutor(int $meetings, int $total, bool $paidFull = false, bool $withReturnTutor = false): array
    {
        $program = Program::factory()->create(['total_meetings' => $meetings, 'price' => $total, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorUser->id]);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 100_000]);

        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id,
            'payment_method' => 'full upfront', 'total_amount' => $total,
            'payment_status' => 'full', 'status' => 'active', 'remaining_meetings' => $meetings,
        ]);
        if ($paidFull) {
            app(AccountingService::class)->createJournal(
                '2026-07-01', "DP enrollment #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}",
                [
                    ['account_code' => '1002', 'debit' => $total, 'credit' => 0],
                    ['account_code' => '2002', 'debit' => 0, 'credit' => $total],
                ],
                'payment', $program->id, $enrollment->id,
            );
        }

        return $withReturnTutor ? [$enrollment, $tutorUser, $tutor] : [$enrollment, $tutorUser];
    }
}
