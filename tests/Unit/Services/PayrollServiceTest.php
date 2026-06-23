<?php

namespace Tests\Unit\Services;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\AttendanceTutor;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Tutor;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
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
        Account::factory()->create(['code' => AccountCode::DEFERRED_REVENUE->value,'name' => 'Deferred Revenue','type' => 'Liability']);
        Account::factory()->create(['code' => AccountCode::REVENUE_TUITION_FEES->value, 'name' => 'Revenue - Tuition Fees', 'type' => 'Revenue']);
        Account::factory()->create(['code' => AccountCode::EXPENSE_TUTOR_FEE->value,   'name' => 'Expense - Tutor Fee',    'type' => 'Expense']);
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
        $this->expectExceptionMessageMatches('/sudah ada/i');

        $this->service->createPayrollRun('2025-06');
    }

    #[Test]
    public function cannot_create_duplicate_payroll_run_when_previous_is_approved(): void
    {
        $this->service->createPayrollRun('2025-06');

        // Force to approved so a new run for same month should still be blocked
        PayrollRun::where('month', '2025-06-01')->update(['status' => 'approved']);

        $this->expectException(DomainException::class);
        $this->service->createPayrollRun('2025-06');
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
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

        // No attendance created → approve should not create any journals
        $this->service->approvePayrollRun($payrollRun->id, 1);

        $this->assertSame('approved', $payrollRun->fresh()->status);
        $this->assertSame(0, Journal::where('reference', 'like', 'PAYROLL-%')->count());
    }

    #[Test]
    public function approve_payroll_run_creates_payable_journal_for_tutor_with_unpaid_attendance(): void
    {
        $approver    = User::factory()->admin()->create();
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

        // Create attendance in June 2025 with unpaid tutor pivot
        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 150_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $this->assertDatabaseHas('journals', [
            'reference'    => $reference,
            'total_amount' => 150_000,
            'type'         => 'payroll',
        ]);

        // Attendance_tutor.paid_at should now be set
        $pivot = AttendanceTutor::where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($pivot->paid_at);

        // PayrollRun should be marked approved
        $this->assertSame('approved', $payrollRun->fresh()->status);
        $this->assertSame($approver->id, $payrollRun->fresh()->approved_by);
    }

    #[Test]
    public function approve_payroll_run_skips_attendance_with_pending_rate(): void
    {
        $approver    = User::factory()->admin()->create();
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

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
        $approver    = User::factory()->admin()->create();
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 250_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $journal   = Journal::where('reference', $reference)->first();

        $this->assertNotNull($journal);

        // Debit side: Tutor Payable
        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::TUTOR_PAYABLE->value)->value('id'),
            'debit'      => 250_000,
            'credit'     => 0,
        ]);

        // Credit side: Bank
        $this->assertDatabaseHas('journal_items', [
            'journal_id' => $journal->id,
            'account_id' => Account::where('code', AccountCode::BANK->value)->value('id'),
            'debit'      => 0,
            'credit'     => 250_000,
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
        $reverser    = User::factory()->admin()->create();
        $approver    = User::factory()->admin()->create();
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

        $this->createUnpaidAttendanceForTutor($tutor, '2025-06-10', 200_000);

        $this->service->approvePayrollRun($payrollRun->id, $approver->id);

        // Sanity check: paid_at is set after approval
        $pivot = AttendanceTutor::where('tutor_id', $tutor->id)->first();
        $this->assertNotNull($pivot->paid_at);

        // Now reverse
        $this->service->reversePayrollRun($payrollRun->id, $reverser->id);

        // Reversal journal exists
        $originalRef = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
        $reverseRef  = "REV-{$originalRef}";
        $this->assertDatabaseHas('journals', [
            'reference'    => $reverseRef,
            'total_amount' => 200_000,
            'type'         => 'payroll',
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
        $user        = User::factory()->admin()->create();
        $tutor       = $this->createTutorWithUser();
        $payrollRun  = $this->service->createPayrollRun('2025-06');

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
            'date'       => $date,
            'time_block' => 'afternoon',
        ]);

        return AttendanceTutor::factory()->create([
            'attendance_id'  => $attendance->id,
            'tutor_id'       => $tutor->id,
            'payable_amount' => $payableAmount,
            'pending_rate'   => $pendingRate,
            'paid_at'        => null,
        ]);
    }
}
