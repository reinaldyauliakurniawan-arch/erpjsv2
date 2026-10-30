<?php

namespace Tests\Unit\Services;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\AttendanceTutor;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Models\PayrollRun;
use App\Models\Tutor;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Critical-path unit tests for PayrollService.
 *
 * Covers the three lifecycle stages of a payroll run:
 *   1. createPayrollRun  — idempotency guard (no duplicate runs for same month)
 *   2. approvePayrollRun — generates Tutor Payable → Bank journals, marks attendance as paid
 *   3. reversePayrollRun — generates reversal journals, unmarks paid attendances
 *
 * Edge cases covered:
 *   - Cannot create payroll run for a month that already has a non-reversed run
 *   - Cannot approve already-approved payroll run
 *   - Cannot reverse a payroll run that isn't approved
 *   - Tutor with pending_rate=true is excluded from payroll
 *   - Tutor already paid is excluded (idempotency)
 *   - Reversing a payroll run unmarks all attendance_tutor.paid_at
 */
class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayrollService::class);

        // Seed accounts required by PayrollService journal entries
        $this->seedRequiredAccounts();
    }

    private function seedRequiredAccounts(): void
    {
        Account::factory()->create(['code' => AccountCode::BANK->value,           'name' => 'Bank',           'type' => 'Asset']);
        Account::factory()->create(['code' => AccountCode::TUTOR_PAYABLE->value,   'name' => 'Tutor Payable',  'type' => 'Liability']);
        Account::factory()->create(['code' => AccountCode::CASH->value,            'name' => 'Cash',           'type' => 'Asset']);
        Account::factory()->create(['code' => AccountCode::DEFERRED_REVENUE->value, 'name' => 'Deferred Revenue', 'type' => 'Liability']);
        Account::factory()->create(['code' => AccountCode::REVENUE_TUITION_FEES->value, 'name' => 'Revenue - Tuition Fees', 'type' => 'Revenue']);
        Account::factory()->create(['code' => AccountCode::EXPENSE_TUTOR_FEE->value,   'name' => 'Expense - Tutor Fee',    'type' => 'Expense']);
        Account::firstOrCreate(
            ['code' => AccountCode::OPENING_BALANCE_EQUITY->value],
            ['name' => 'Ekuitas Saldo Awal', 'type' => 'Equity']
        );
        // Account 5006 is seeded by a migration, so guard against a duplicate.
        Account::firstOrCreate(
            ['code' => AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value],
            ['name' => 'Beban Gaji Tutor Tetap', 'type' => 'Expense']
        );
    }

    // =========================================================
    //  TUTOR TETAP (SALARIED) — PAYROLL
    // =========================================================

    #[Test]
    public function approve_payroll_run_posts_fixed_salary_for_permanent_tutor(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $tutor->update([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2025-06-01',
        ]);

        $run = $this->service->createPayrollRun('2025-06');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $ref = "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY";
        $journal = Journal::where('reference', $ref)->first();
        $this->assertNotNull($journal);
        $this->assertEquals(3_500_000, $journal->total_amount);

        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value)->value('id'),
            'debit' => 3_500_000,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::BANK->value)->value('id'),
            'credit' => 3_500_000,
        ]);
    }

    #[Test]
    public function permanent_tutor_salary_is_prorated_for_a_partial_first_month(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        // Mulai 16 Juni: 15 hari dari 30 hari → setengah gaji.
        $tutor->update([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_000_000,
            'salaried_since' => '2025-06-16',
        ]);

        $run = $this->service->createPayrollRun('2025-06');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $journal = Journal::where('reference', "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(1_500_000, $journal->total_amount);
    }

    #[Test]
    public function permanent_tutor_salary_is_prorated_for_a_partial_last_month(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        // Berhenti tetap: hari terakhir 10 Feb 2025 (bulan 28 hari) → 10/28.
        $tutor->update([
            'employment_type' => 'freelance',
            'monthly_salary' => 2_800_000,
            'salaried_since' => '2025-01-01',
            'salaried_until' => '2025-02-10',
        ]);

        $run = $this->service->createPayrollRun('2025-02');
        $this->service->approvePayrollRun($run->id, $approver->id);

        // 2_800_000 * 10 / 28 = 1_000_000
        $this->assertEquals(
            1_000_000,
            Journal::where('reference', "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY")->value('total_amount')
        );
    }

    #[Test]
    public function permanent_tutor_salary_stops_after_salaried_until(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $tutor->update([
            'employment_type' => 'freelance',
            'monthly_salary' => 3_000_000,
            'salaried_since' => '2025-06-01',
            'salaried_until' => '2025-06-30',
        ]);

        // Juni: masih penuh.
        $june = $this->service->createPayrollRun('2025-06');
        $this->service->approvePayrollRun($june->id, $approver->id);
        $this->assertEquals(
            3_000_000,
            Journal::where('reference', "PAYROLL-{$june->id}-TUTOR-{$tutor->id}-SALARY")->value('total_amount')
        );

        // Juli: sudah lewat salaried_until → tidak ada jurnal gaji.
        $july = $this->service->createPayrollRun('2025-07');
        $this->service->approvePayrollRun($july->id, $approver->id);
        $this->assertSame(0, Journal::where('reference', "PAYROLL-{$july->id}-TUTOR-{$tutor->id}-SALARY")->count());
    }

    #[Test]
    public function permanent_tutor_salary_not_posted_for_month_before_salaried_since(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $tutor->update([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2025-09-01',
        ]);

        $run = $this->service->createPayrollRun('2025-06');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $this->assertSame(0, Journal::where('reference', 'like', 'PAYROLL-%-SALARY')->count());
    }

    #[Test]
    public function reverse_payroll_run_reverses_permanent_tutor_salary(): void
    {
        $user = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $tutor->update([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2025-06-01',
        ]);

        $run = $this->service->createPayrollRun('2025-06');
        $this->service->approvePayrollRun($run->id, $user->id);
        $this->service->reversePayrollRun($run->id, $user->id);

        $revRef = "REV-PAYROLL-{$run->id}-TUTOR-{$tutor->id}-SALARY";
        $this->assertDatabaseHas('journals', ['reference' => $revRef, 'total_amount' => 3_500_000]);

        // Net effect on the salary expense account is zero.
        $expenseId = Account::where('code', AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value)->value('id');
        $net = JournalItem::where('account_id', $expenseId)->sum('debit')
             - JournalItem::where('account_id', $expenseId)->sum('credit');
        $this->assertEquals(0, $net);
    }

    // =========================================================
    //  CREATE PAYROLL RUN
    // =========================================================

    #[Test]
    public function create_payroll_run_creates_pending_run_for_given_month(): void
    {
        $run = $this->service->createPayrollRun('2025-06');

        $this->assertInstanceOf(PayrollRun::class, $run);
        $this->assertSame('2025-06-01', $run->month->toDateString());
        $this->assertSame('pending', $run->status);
    }

    #[Test]
    public function create_payroll_run_normalizes_month_to_first_day(): void
    {
        // Passing mid-month date should still normalize to first day
        $run = $this->service->createPayrollRun('2025-06-15');

        $this->assertSame('2025-06-01', $run->month->toDateString());
    }

    #[Test]
    public function cannot_create_duplicate_payroll_run_for_same_month_when_previous_is_pending(): void
    {
        $this->service->createPayrollRun('2025-06');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/belum di-approve/i');

        $this->service->createPayrollRun('2025-06');
    }

    #[Test]
    public function can_create_supplementary_payroll_run_when_previous_is_approved(): void
    {
        // Honor yang telat masuk setelah payroll bulan itu di-approve butuh
        // pembayaran susulan lewat run kedua.
        $first = $this->service->createPayrollRun('2025-06');
        $first->update(['status' => 'approved']);

        $supplementary = $this->service->createPayrollRun('2025-06');

        $this->assertSame('pending', $supplementary->status);
        $this->assertSame(2, PayrollRun::whereDate('month', '2025-06-01')->count());
    }

    #[Test]
    public function can_create_new_payroll_run_after_previous_was_reversed(): void
    {
        $first = $this->service->createPayrollRun('2025-06');
        $first->update(['status' => 'reversed']);

        // Should not throw
        $second = $this->service->createPayrollRun('2025-06');

        $this->assertNotEquals($first->id, $second->id);
        $this->assertSame('pending', $second->status);
    }

    // =========================================================
    //  APPROVE PAYROLL RUN
    // =========================================================

    #[Test]
    public function approve_payroll_run_skips_tutors_with_no_unpaid_attendance(): void
    {
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        // No attendance created → approve should not create any journals
        $this->service->approvePayrollRun($payrollRun->id, 1);

        $this->assertSame('approved', $payrollRun->fresh()->status);
        $this->assertSame(0, Journal::where('reference', 'like', 'PAYROLL-%')->count());
    }

    #[Test]
    public function approve_payroll_run_creates_payable_journal_for_tutor_with_unpaid_attendance(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        // Create attendance in June 2025 with unpaid tutor pivot
        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 150_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $this->assertDatabaseHas('journals', [
            'reference' => $reference,
            'total_amount' => 150_000,
            'type' => 'payroll',
        ]);

        // Attendance_tutor.paid_at should now be set
        $pivot = AttendanceTutor::where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($pivot->paid_at);

        // PayrollRun should be marked approved
        $this->assertSame('approved', $payrollRun->fresh()->status);
        $this->assertSame($approver->id, $payrollRun->fresh()->approved_by);
    }

    // =========================================================
    //  PERIODE HONOR 26 – 25
    // =========================================================

    #[Test]
    public function payroll_month_pays_meetings_from_26th_of_previous_month_to_25th(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();

        $before = $this->createUnpaidAttendanceForTutor($tutor, '2026-09-25', 10_000); // payroll September
        $first = $this->createUnpaidAttendanceForTutor($tutor, '2026-09-26', 20_000);  // payroll Oktober
        $last = $this->createUnpaidAttendanceForTutor($tutor, '2026-10-25', 40_000);   // payroll Oktober
        $next = $this->createUnpaidAttendanceForTutor($tutor, '2026-10-26', 80_000);   // payroll November

        $run = $this->service->createPayrollRun('2026-10');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $journal = Journal::where('reference', "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-PAY")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(60_000, $journal->total_amount);
        $this->assertNotNull($first->fresh()->paid_at);
        $this->assertNotNull($last->fresh()->paid_at);
        $this->assertNull($before->fresh()->paid_at);
        $this->assertNull($next->fresh()->paid_at);

        // Pertemuan 26 Okt baru terbayar di payroll November.
        $nov = $this->service->createPayrollRun('2026-11');
        $this->service->approvePayrollRun($nov->id, $approver->id);
        $this->assertEquals(80_000, Journal::where('reference', "PAYROLL-{$nov->id}-TUTOR-{$tutor->id}-PAY")->value('total_amount'));
        $this->assertSame($nov->id, $next->fresh()->payroll_run_id);
        $this->assertNull($before->fresh()->paid_at);
    }

    #[Test]
    public function january_payroll_period_starts_on_26_december_of_previous_year(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $dec = $this->createUnpaidAttendanceForTutor($tutor, '2026-12-27', 50_000);

        $run = $this->service->createPayrollRun('2027-01');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $this->assertSame($run->id, $dec->fresh()->payroll_run_id);
    }

    #[Test]
    public function reverse_fallback_only_unmarks_meetings_in_the_26_to_25_period(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $run = $this->service->createPayrollRun('2026-10');
        $this->service->approvePayrollRun($run->id, $approver->id);

        // Run lama tanpa payroll_run_id di baris presensi: jalur fallback per periode.
        Journal::factory()->create(['reference' => "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-PAY", 'total_amount' => 30_000]);
        $inPeriod = $this->createUnpaidAttendanceForTutor($tutor, '2026-09-28', 30_000);
        $outPeriod = $this->createUnpaidAttendanceForTutor($tutor, '2026-10-28', 30_000);
        AttendanceTutor::whereIn('id', [$inPeriod->id, $outPeriod->id])->update(['paid_at' => now()]);

        $this->service->reversePayrollRun($run->id, $approver->id);

        $this->assertNull($inPeriod->fresh()->paid_at);
        $this->assertNotNull($outPeriod->fresh()->paid_at);
    }

    // =========================================================
    //  PENYESUAIAN HONOR (B2B, tunjangan, koreksi, saldo awal)
    // =========================================================

    #[Test]
    public function adjustment_posts_expense_or_opening_journal_against_tutor_payable(): void
    {
        $tutor = $this->createTutorWithUser();
        $payable = Account::where('code', AccountCode::TUTOR_PAYABLE->value)->value('id');

        $honor = $this->service->addAdjustment($tutor->id, '2026-10-20', '855000', 'honor', 'B2B RU PUTRI 9 pertemuan');
        $this->assertDatabaseHas('journal_items', ['journal_id' => $honor->journal_id, 'account_id' => Account::where('code', AccountCode::EXPENSE_TUTOR_FEE->value)->value('id'), 'debit' => 855000]);
        $this->assertDatabaseHas('journal_items', ['journal_id' => $honor->journal_id, 'account_id' => $payable, 'credit' => 855000]);

        $opening = $this->service->addAdjustment($tutor->id, '2026-10-01', '120000', 'saldo_awal', 'Honor 26–30 Sep');
        $this->assertDatabaseHas('journal_items', ['journal_id' => $opening->journal_id, 'account_id' => Account::where('code', AccountCode::OPENING_BALANCE_EQUITY->value)->value('id'), 'debit' => 120000]);

        $minus = $this->service->addAdjustment($tutor->id, '2026-10-20', '-60000', 'honor', 'Klaim dobel');
        $this->assertDatabaseHas('journal_items', ['journal_id' => $minus->journal_id, 'account_id' => $payable, 'debit' => 60000]);

        $tb = JournalItem::query()->selectRaw('SUM(debit) - SUM(credit) v')->value('v');
        $this->assertEquals(0, (float) $tb);
    }

    #[Test]
    public function approve_pays_meeting_fees_plus_adjustments_in_period_only(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $this->createUnpaidAttendanceForTutor($tutor, '2026-10-10', 300_000);
        $in = $this->service->addAdjustment($tutor->id, '2026-10-25', '50000', 'tunjangan', 'Tunjangan prestasi');
        $minus = $this->service->addAdjustment($tutor->id, '2026-09-28', '-60000', 'honor', 'Klaim dobel');
        $out = $this->service->addAdjustment($tutor->id, '2026-10-26', '70000', 'honor', 'Masuk November');

        $run = $this->service->createPayrollRun('2026-10');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $this->assertEquals(290_000, Journal::where('reference', "PAYROLL-{$run->id}-TUTOR-{$tutor->id}-PAY")->value('total_amount'));
        $this->assertSame($run->id, $in->fresh()->payroll_run_id);
        $this->assertSame($run->id, $minus->fresh()->payroll_run_id);
        $this->assertNull($out->fresh()->paid_at);

        $this->service->reversePayrollRun($run->id, $approver->id);
        $this->assertNull($in->fresh()->paid_at);
        $this->assertNull($minus->fresh()->payroll_run_id);
    }

    #[Test]
    public function paid_adjustment_cannot_be_deleted_but_unpaid_one_removes_its_journal(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $paid = $this->service->addAdjustment($tutor->id, '2026-10-05', '100000', 'honor', 'B2B');
        $run = $this->service->createPayrollRun('2026-10');
        $this->service->approvePayrollRun($run->id, $approver->id);

        $this->expectException(DomainException::class);
        try {
            $this->service->deleteAdjustment($paid->id);
        } finally {
            $later = $this->service->addAdjustment($tutor->id, '2026-10-27', '40000', 'honor', 'Susulan');
            $jid = $later->journal_id;
            $this->service->deleteAdjustment($later->id);
            $this->assertDatabaseMissing('journals', ['id' => $jid]);
            $this->assertDatabaseMissing('tutor_fee_adjustments', ['id' => $later->id]);
        }
    }

    #[Test]
    public function preview_matches_what_approve_will_pay(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $permanent = $this->createTutorWithUser();
        $permanent->update(['employment_type' => 'permanent', 'monthly_salary' => 3_000_000, 'salaried_since' => '2026-10-01']);
        $this->createUnpaidAttendanceForTutor($tutor, '2026-09-30', 60_000);
        $this->service->addAdjustment($tutor->id, '2026-10-20', '40000', 'tunjangan', 'Tunjangan');

        $preview = $this->service->preview('2026-10-01')->keyBy('tutor');
        $this->assertEquals(100_000, $preview[$tutor->user->name]['total']);
        $this->assertEquals(3_000_000, $preview[$permanent->user->name]['total']);

        $run = $this->service->createPayrollRun('2026-10');
        $this->service->approvePayrollRun($run->id, $approver->id);
        $this->assertTrue($this->service->preview('2026-10-01')->isEmpty());
    }

    #[Test]
    public function payroll_period_helper_maps_dates_to_payroll_month(): void
    {
        $this->assertSame('2026-10-01', PayrollPeriod::monthFor('2026-09-26')->toDateString());
        $this->assertSame('2026-10-01', PayrollPeriod::monthFor('2026-10-25')->toDateString());
        $this->assertSame('2026-11-01', PayrollPeriod::monthFor('2026-10-26')->toDateString());
        $this->assertSame('2027-01-01', PayrollPeriod::monthFor('2026-12-31')->toDateString());
        [$from, $to] = PayrollPeriod::forMonth('2026-03-01');
        $this->assertSame(['2026-02-26', '2026-03-25'], [$from->toDateString(), $to->toDateString()]);
    }

    #[Test]
    public function approve_payroll_run_skips_attendance_with_pending_rate(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        // Create attendance where pending_rate=true — should be skipped
        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 150_000, pendingRate: true);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        $this->assertSame(0, Journal::where('reference', 'like', 'PAYROLL-%')->count());

        // Pending-rate attendance should remain unpaid
        $pivot = AttendanceTutor::where('tutor_id', $tutor->id)->first();
        $this->assertNull($pivot->paid_at);
    }

    #[Test]
    public function cannot_approve_already_approved_payroll_run(): void
    {
        $payrollRun = PayrollRun::factory()->approved()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/sudah di-approve/i');

        $this->service->approvePayrollRun($payrollRun->id, 1);
    }

    #[Test]
    public function approve_payroll_run_uses_correct_account_codes_in_journal(): void
    {
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 250_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $journal = Journal::where('reference', $reference)->first();

        $this->assertNotNull($journal);

        // Debit side: Tutor Payable
        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::TUTOR_PAYABLE->value)->value('id'),
            'debit' => 250_000,
            'credit' => 0,
        ]);

        // Credit side: Bank
        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::BANK->value)->value('id'),
            'debit' => 0,
            'credit' => 250_000,
        ]);
    }

    // =========================================================
    //  REVERSE PAYROLL RUN
    // =========================================================

    #[Test]
    public function cannot_reverse_payroll_run_that_is_not_approved(): void
    {
        $payrollRun = PayrollRun::factory()->create(['status' => 'pending']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/hanya .* approved/i');

        $this->service->reversePayrollRun($payrollRun->id, 1);
    }

    #[Test]
    public function reverse_payroll_run_creates_reversal_journal_and_unmarks_paid(): void
    {
        $reverser = User::factory()->admin()->create();
        $approver = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 200_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        // Sanity check: paid_at is set after approval
        $pivot = AttendanceTutor::where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($pivot->paid_at);

        // Now reverse
        $this->service->reversePayrollRun($payrollRun->id, $reverser->id);

        // Reversal journal exists
        $originalRef = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $reverseRef = "REV-{$originalRef}";
        $this->assertDatabaseHas('journals', [
            'reference' => $reverseRef,
            'total_amount' => 200_000,
            'type' => 'payroll',
        ]);

        // paid_at should be null again
        $pivot->refresh();
        $this->assertNull($pivot->paid_at);

        // PayrollRun marked reversed
        $this->assertSame('reversed', $payrollRun->fresh()->status);
        $this->assertSame($reverser->id, $payrollRun->fresh()->reversed_by);
    }

    #[Test]
    public function reverse_payroll_run_is_idempotent_for_already_reversed_journals(): void
    {
        $user = User::factory()->admin()->create();
        $tutor = $this->createTutorWithUser();
        $payrollRun = $this->service->createPayrollRun('2025-06');

        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 100_000);
        $this->service->approvePayrollRun($payrollRun->id, $user->id);

        // First reverse — should succeed
        $this->service->reversePayrollRun($payrollRun->id, $user->id);

        // Manually re-approve and reverse again — should not throw IdempotencyException
        $payrollRun->refresh();
        $payrollRun->update(['status' => 'approved']);

        // Second reverse — should skip journals that already have a REV- reference
        $this->service->reversePayrollRun($payrollRun->id, $user->id);

        // Only ONE reversal journal should exist (no duplicate)
        $reverseRef = "REV-PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $this->assertSame(1, Journal::where('reference', $reverseRef)->count());
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function createTutorWithUser(): Tutor
    {
        $user = User::factory()->tutor()->create();

        return Tutor::factory()->forUser($user)->create();
    }

    private function createUnpaidAttendanceForTutor(
        Tutor $tutor,
        string $date,
        int $payableAmount,
        bool $pendingRate = false,
    ): AttendanceTutor {
        $attendance = Attendance::factory()->create([
            'date' => $date,
            'time_block' => 'afternoon',
        ]);

        return AttendanceTutor::factory()->create([
            'attendance_id' => $attendance->id,
            'tutor_id' => $tutor->id,
            'payable_amount' => $payableAmount,
            'pending_rate' => $pendingRate,
            'paid_at' => null,
        ]);
    }
}
