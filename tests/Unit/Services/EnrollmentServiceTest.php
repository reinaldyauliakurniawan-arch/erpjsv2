<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnrollmentService::class);

        // Akun wajib untuk accounting
        Account::factory()->create(['code' => '1101', 'name' => 'Cash/Bank']);
        Account::factory()->create(['code' => '2201', 'name' => 'Deferred Revenue']);
    }

    // =========================================================
    //  HELPER
    // =========================================================

    private function makePrivateProgram(array $attrs = []): Program
    {
        return Program::factory()->create(array_merge([
            'type'           => 'private',
            'price'          => 1_500_000,
            'total_meetings' => 8,
            'min_quota'      => 1,
        ], $attrs));
    }

    private function makeGroupProgram(array $attrs = []): Program
    {
        return Program::factory()->create(array_merge([
            'type'           => 'group',
            'price'          => 800_000,
            'total_meetings' => 10,
            'min_quota'      => 3,
        ], $attrs));
    }

    private function makeClassroom(int $capacity = 5): Classroom
    {
        return Classroom::factory()->create(['capacity' => $capacity]);
    }

    private function baseData(Program $program, array $overrides = []): array
    {
        $classroom = $this->makeClassroom();

        return array_merge([
            'program_id'      => $program->id,
            'enrollment_date' => '2025-01-10',
            'expiry_date'     => '2025-06-10',
            'payment_method'  => 'full upfront',
            'new_student'     => [
                'name'  => 'Andi Pratama',
                'email' => 'andi@example.com',
                'phone' => '08111111111',
            ],
            'schedules' => [
                ['classroom_id' => $classroom->id, 'day' => 'Monday', 'time_block' => '08:00-09:30'],
            ],
        ], $overrides);
    }

    // =========================================================
    //  PRIVATE CLASS — HAPPY PATH
    // =========================================================

    #[Test]
    public function it_enrolls_student_into_private_class_and_creates_user()
    {
        $program = $this->makePrivateProgram();
        $tutor   = Tutor::factory()->withUser()->create();

        $enrollment = $this->service->enroll(array_merge(
            $this->baseData($program),
            ['tutor_ids' => [$tutor->id]]
        ));

        $this->assertDatabaseHas('users', ['email' => 'andi@example.com', 'role' => 'student']);
        $this->assertDatabaseHas('enrollments', [
            'id'                 => $enrollment->id,
            'status'             => 'active',
            'payment_status'     => PaymentStatus::PENDING->value,
            'remaining_meetings' => 8,
        ]);
    }

    #[Test]
    public function it_auto_creates_class_session_for_private_program()
    {
        $program = $this->makePrivateProgram();

        $enrollment = $this->service->enroll($this->baseData($program));

        $session = ClassSession::find($enrollment->class_session_id);
        $this->assertNotNull($session);
        $this->assertStringContainsString('Private_', $session->name);
    }

    #[Test]
    public function it_names_private_session_with_tutor_prefix_when_tutor_provided()
    {
        $program = $this->makePrivateProgram();
        $tutor   = Tutor::factory()->withUser(['name' => 'Budi Santoso'])->create();

        $enrollment = $this->service->enroll(array_merge(
            $this->baseData($program),
            ['tutor_ids' => [$tutor->id]]
        ));

        $session = ClassSession::find($enrollment->class_session_id);
        $this->assertStringStartsWith('Budi_', $session->name);
    }

    #[Test]
    public function it_attaches_tutors_with_pending_status()
    {
        $program = $this->makePrivateProgram();
        $tutor   = Tutor::factory()->withUser()->create();

        $enrollment = $this->service->enroll(array_merge(
            $this->baseData($program),
            ['tutor_ids' => [$tutor->id]]
        ));

        $this->assertDatabaseHas('enrollment_tutor', [
            'enrollment_id' => $enrollment->id,
            'tutor_id'      => $tutor->id,
            'status'        => 'pending',
        ]);
    }

    #[Test]
    public function it_creates_journal_entry_for_full_upfront_payment()
    {
        $program = $this->makePrivateProgram(['price' => 1_500_000]);

        $this->service->enroll($this->baseData($program));

        $this->assertDatabaseHas('journals', ['total_amount' => 1_500_000]);
    }

    #[Test]
    public function it_does_not_create_journal_when_no_payment_made()
    {
        $program = $this->makePrivateProgram();

        // payment_method = installment, installments kosong (bayar nol)
        $data = array_merge($this->baseData($program), [
            'payment_method' => 'installment',
            'installments'   => [], // tidak ada cicilan awal
        ]);

        $this->service->enroll($data);

        $this->assertDatabaseCount('journals', 0);
    }

    // =========================================================
    //  GROUP CLASS — QUOTA & WAITLIST
    // =========================================================

    #[Test]
    public function group_enrollment_sets_waitlist_when_below_min_quota()
    {
        $program  = $this->makeGroupProgram(['min_quota' => 3]);
        $session  = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'pending']);
        $classroom = $this->makeClassroom(10);

        $data = array_merge($this->baseData($program), [
            'class_session_id' => $session->id,
            'schedules'        => [
                ['classroom_id' => $classroom->id, 'day' => 'Tuesday', 'time_block' => '10:00-11:30'],
            ],
        ]);

        $enrollment = $this->service->enroll($data);

        $this->assertEquals('waitlist', $enrollment->status);
    }

    #[Test]
    public function group_enrollment_activates_all_waitlist_when_quota_reached()
    {
        $program   = $this->makeGroupProgram(['min_quota' => 2]);
        $session   = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'pending']);
        $classroom = $this->makeClassroom(10);

        // Enrollment pertama → waitlist
        $waitlisted = Enrollment::factory()->create([
            'class_session_id' => $session->id,
            'status'           => 'waitlist',
        ]);

        // Enrollment kedua → mencapai min_quota, semua jadi active
        $data = array_merge($this->baseData($program), [
            'class_session_id' => $session->id,
            'new_student'      => ['name' => 'Baru Siswa', 'email' => 'baru@example.com'],
            'schedules'        => [
                ['classroom_id' => $classroom->id, 'day' => 'Wednesday', 'time_block' => '09:00-10:30'],
            ],
        ]);

        $this->service->enroll($data);

        $this->assertEquals('active', $waitlisted->fresh()->status);
    }

    #[Test]
    public function group_enrollment_throws_when_class_session_not_provided()
    {
        $this->expectException(DomainException::class);

        $program = $this->makeGroupProgram();
        $this->service->enroll($this->baseData($program)); // tidak ada class_session_id
    }

    // =========================================================
    //  ROOM OCCUPANCY VALIDATION
    // =========================================================

    #[Test]
    public function it_throws_domain_exception_when_room_is_full()
    {
        $this->expectException(DomainException::class);

        $program   = $this->makePrivateProgram();
        $classroom = $this->makeClassroom(1); // kapasitas 1

        // Isi dulu ruangan
        Schedule::factory()->create([
            'classroom_id' => $classroom->id,
            'day'          => 'Monday',
            'time_block'   => '08:00-09:30',
        ]);

        $data = $this->baseData($program, [
            'schedules' => [
                ['classroom_id' => $classroom->id, 'day' => 'Monday', 'time_block' => '08:00-09:30'],
            ],
        ]);

        $this->service->enroll($data);
    }

    // =========================================================
    //  INSTALLMENT
    // =========================================================

    #[Test]
    public function it_creates_installments_when_payment_method_is_installment()
    {
        $program = $this->makePrivateProgram(['price' => 1_500_000]);

        $data = array_merge($this->baseData($program), [
            'payment_method' => 'installment',
            'installments'   => [
                ['amount' => 750_000, 'due_date' => '2025-01-15'],
                ['amount' => 750_000, 'due_date' => '2025-02-15'],
            ],
        ]);

        $enrollment = $this->service->enroll($data);

        $this->assertCount(2, $enrollment->installments);
        $this->assertDatabaseHas('installments', ['enrollment_id' => $enrollment->id, 'amount' => 750_000]);
    }

    #[Test]
    public function it_creates_journal_only_for_first_installment_amount()
    {
        $program = $this->makePrivateProgram(['price' => 1_500_000]);

        $data = array_merge($this->baseData($program), [
            'payment_method' => 'installment',
            'installments'   => [
                ['amount' => 500_000, 'due_date' => '2025-01-15'],
                ['amount' => 1_000_000, 'due_date' => '2025-02-15'],
            ],
        ]);

        $this->service->enroll($data);

        // Journal dibuat hanya untuk cicilan pertama
        $this->assertDatabaseHas('journals', ['total_amount' => 500_000]);
        $this->assertDatabaseMissing('journals', ['total_amount' => 1_500_000]);
    }

    // =========================================================
    //  SCHEDULE CREATION
    // =========================================================

    #[Test]
    public function it_creates_schedules_for_enrollment()
    {
        $program   = $this->makePrivateProgram();
        $classroom = $this->makeClassroom();

        $data = $this->baseData($program, [
            'schedules' => [
                ['classroom_id' => $classroom->id, 'day' => 'Monday',    'time_block' => '08:00-09:30'],
                ['classroom_id' => $classroom->id, 'day' => 'Wednesday', 'time_block' => '08:00-09:30'],
            ],
        ]);

        $enrollment = $this->service->enroll($data);

        $this->assertCount(2, $enrollment->schedules);
    }
}
