<?php

namespace Tests\Unit\Services;

use App\Enums\AccountCode;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Journal;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fokus: perlakuan fee tutor tetap (salaried) di AttendanceService.
 */
class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);

        foreach ([
            [AccountCode::DEFERRED_REVENUE->value, 'Deferred Revenue', 'Liability'],
            [AccountCode::ACCOUNTS_RECEIVABLE->value, 'Piutang', 'Asset'],
            [AccountCode::REVENUE_TUITION_FEES->value, 'Revenue Tuition', 'Revenue'],
            [AccountCode::EXPENSE_TUTOR_FEE->value, 'Beban Gaji Tutor', 'Expense'],
            [AccountCode::TUTOR_PAYABLE->value, 'Utang Tutor', 'Liability'],
        ] as [$code, $name, $type]) {
            Account::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }
    }

    private function makeSessionWithEnrollment(int $totalMeetings = 8): array
    {
        $program = Program::factory()->create(['total_meetings' => $totalMeetings, 'price' => 800_000]);
        $session = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id,
            'class_session_id' => $session->id,
            'status' => 'active',
            'remaining_meetings' => $totalMeetings,
            'total_amount' => 800_000,
            'payment_method' => 'full upfront',
            'payment_status' => 'full',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'class_session_id' => $session->id,
            'day' => 'Senin',
            'time_block' => '09:00-10:30',
        ]);

        return [$session, $enrollment, $program];
    }

    private function markData(ClassSession $session, Enrollment $enrollment, User $tutorUser, string $date): array
    {
        return [
            'class_session_id' => $session->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
            'classroom_id' => Classroom::factory()->create()->id,
            'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ];
    }

    #[Test]
    public function salaried_tutor_marking_attendance_posts_no_per_meeting_fee_journal(): void
    {
        [$session, $enrollment, $program] = $this->makeSessionWithEnrollment();

        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->forUser($tutorUser)->create([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2026-09-01',
        ]);
        // Rate ada, tapi karena tutor tetap tidak boleh dipakai.
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 200_000]);

        $attendance = $this->service->markAttendance(
            $this->markData($session, $enrollment, $tutorUser, '2026-09-10')
        );

        $this->assertSame(0, Journal::where('reference', "TUTOR-PAY-{$attendance->id}-{$tutor->id}")->count());

        $pivot = $attendance->tutors()->where('tutor_id', $tutor->id)->first()->pivot;
        $this->assertEquals(0, $pivot->payable_amount);
        $this->assertFalse((bool) $pivot->pending_rate);

        // Revenue recognition tetap jalan.
        $this->assertSame(1, Journal::where('reference', "REV-REC-{$attendance->id}-{$enrollment->id}")->count());
    }

    #[Test]
    public function tutor_meeting_before_salaried_since_still_accrues_freelance_fee(): void
    {
        [$session, $enrollment, $program] = $this->makeSessionWithEnrollment();

        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->forUser($tutorUser)->create([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2026-09-01',
        ]);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 200_000]);

        $attendance = $this->service->markAttendance(
            $this->markData($session, $enrollment, $tutorUser, '2026-08-20')
        );

        $journal = Journal::where('reference', "TUTOR-PAY-{$attendance->id}-{$tutor->id}")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(200_000, $journal->total_amount);

        $pivot = $attendance->tutors()->where('tutor_id', $tutor->id)->first()->pivot;
        $this->assertEquals(200_000, $pivot->payable_amount);
    }

    #[Test]
    public function tutor_meeting_after_salaried_until_accrues_freelance_fee_again(): void
    {
        [$session, $enrollment, $program] = $this->makeSessionWithEnrollment();

        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->forUser($tutorUser)->create([
            'employment_type' => 'freelance', // sudah balik freelance
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2026-09-01',
            'salaried_until' => '2026-11-30',
        ]);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 200_000]);

        $attendance = $this->service->markAttendance(
            $this->markData($session, $enrollment, $tutorUser, '2026-12-05')
        );

        $this->assertEquals(
            200_000,
            Journal::where('reference', "TUTOR-PAY-{$attendance->id}-{$tutor->id}")->value('total_amount')
        );
    }

    #[Test]
    public function freelance_tutor_accrues_fee_as_before(): void
    {
        [$session, $enrollment, $program] = $this->makeSessionWithEnrollment();

        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->forUser($tutorUser)->create(); // freelance default
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 150_000]);

        $attendance = $this->service->markAttendance(
            $this->markData($session, $enrollment, $tutorUser, '2026-09-10')
        );

        $this->assertEquals(
            150_000,
            Journal::where('reference', "TUTOR-PAY-{$attendance->id}-{$tutor->id}")->value('total_amount')
        );
    }
}
