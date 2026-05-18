<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tutorUser;
    private Tutor $tutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = User::factory()->create(['role' => 'admin']);
        $this->tutorUser = User::factory()->create(['role' => 'tutor']);
        $this->tutor     = Tutor::factory()->create(['user_id' => $this->tutorUser->id]);

        // Akun untuk recognition revenue saat attendance
        Account::factory()->create(['code' => '2201', 'name' => 'Deferred Revenue']);
        Account::factory()->create(['code' => '4101', 'name' => 'Tuition Fees Revenue']);
    }

    // =========================================================
    //  ADMIN — INDEX
    // =========================================================

    #[Test]
    public function admin_can_view_attendance_list()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertViewIs('admin.attendance.index');
    }

    #[Test]
    public function admin_can_delete_attendance()
    {
        $attendance = Attendance::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.attendance.destroy', $attendance->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    // =========================================================
    //  TUTOR — ABSENSI
    // =========================================================

    #[Test]
    public function tutor_can_view_own_attendance()
    {
        $this->actingAs($this->tutorUser)
            ->get(route('tutor.attendance.index'))
            ->assertOk();
    }

    #[Test]
    public function tutor_can_create_attendance_and_reduces_remaining_meetings()
    {
        $enrollment = Enrollment::factory()->create([
            'remaining_meetings' => 5,
            'status'             => 'active',
        ]);

        $schedule = Schedule::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($this->tutorUser)
            ->post(route('tutor.attendance.store'), [
                'schedule_id'   => $schedule->id,
                'enrollment_id' => $enrollment->id,
                'tutor_id'      => $this->tutor->id,
                'date'          => '2025-01-20',
                'notes'         => 'Session berjalan lancar',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(4, $enrollment->fresh()->remaining_meetings);
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $enrollment->id]);
    }

    #[Test]
    public function attendance_creates_revenue_recognition_journal()
    {
        $enrollment = Enrollment::factory()->create([
            'remaining_meetings' => 8,
            'total_amount'       => 800_000,
            'status'             => 'active',
        ]);

        $program = $enrollment->program;
        $program->update(['total_meetings' => 8]);

        $schedule = Schedule::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($this->tutorUser)
            ->post(route('tutor.attendance.store'), [
                'schedule_id'   => $schedule->id,
                'enrollment_id' => $enrollment->id,
                'tutor_id'      => $this->tutor->id,
                'date'          => '2025-01-20',
            ]);

        // Revenue per meeting = 800_000 / 8 = 100_000
        $this->assertDatabaseHas('journals', ['total_amount' => 100_000]);
    }

    #[Test]
    public function tutor_cannot_record_duplicate_attendance_same_session()
    {
        $enrollment = Enrollment::factory()->create(['remaining_meetings' => 5, 'status' => 'active']);
        $schedule   = Schedule::factory()->create(['enrollment_id' => $enrollment->id]);

        Attendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'schedule_id'   => $schedule->id,
            'date'          => '2025-01-20',
        ]);

        $this->actingAs($this->tutorUser)
            ->post(route('tutor.attendance.store'), [
                'schedule_id'   => $schedule->id,
                'enrollment_id' => $enrollment->id,
                'tutor_id'      => $this->tutor->id,
                'date'          => '2025-01-20',
            ])
            ->assertSessionHasErrors();
    }

    #[Test]
    public function tutor_can_delete_own_attendance()
    {
        $attendance = Attendance::factory()->create(['tutor_id' => $this->tutor->id]);

        $this->actingAs($this->tutorUser)
            ->delete(route('tutor.attendance.destroy', $attendance->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    #[Test]
    public function student_cannot_access_tutor_attendance_routes()
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('tutor.attendance.index'))
            ->assertForbidden();
    }
}
