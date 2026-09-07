<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Tutor;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AttendanceService;
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
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->tutorUser = User::factory()->create(['role' => 'tutor']);
        $this->tutor = Tutor::factory()->create(['user_id' => $this->tutorUser->id]);

        foreach (['1001', '1002', '1003', '2002', '2003', '4101', '5001'] as $code) {
            Account::factory()->create(['code' => $code, 'name' => "Acc {$code}"]);
        }
    }

    /** Bikin kelas aktif + tutor confirmed + 1 enrollment aktif yang sudah dibayar penuh. */
    private function activeClassWithStudent(int $meetings = 8, int $total = 800_000): array
    {
        $program = Program::factory()->create(['total_meetings' => $meetings, 'price' => $total, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $cs->tutors()->attach($this->tutor->id, ['status' => 'confirmed']);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id,
            'payment_method' => 'full upfront', 'total_amount' => $total,
            'payment_status' => 'full', 'status' => 'active', 'remaining_meetings' => $meetings,
        ]);
        app(AccountingService::class)->createJournal(
            '2026-07-01', "DP #{$enrollment->id}", "PAYMENT-ENROLL-{$enrollment->id}",
            [
                ['account_code' => '1002', 'debit' => $total, 'credit' => 0],
                ['account_code' => '2002', 'debit' => 0, 'credit' => $total],
            ],
            'payment', $program->id, $enrollment->id,
        );

        return [$cs, $enrollment, Classroom::factory()->create()];
    }

    private function storePayload(ClassSession $cs, Enrollment $e, Classroom $room, string $date = '2026-07-05'): array
    {
        return [
            'class_session_id' => $cs->id,
            'date' => $date,
            'time_block' => '08:00-09:30',
            'classroom_id' => $room->id,
            'mode' => 'own',
            'marked_by' => $this->tutorUser->id,
            'students' => [['enrollment_id' => $e->id, 'is_present' => true]],
        ];
    }

    // ── ADMIN ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_attendance_list()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertViewIs('admin.attendance.index');
    }

    #[Test]
    public function admin_can_delete_attendance_and_it_reverses_the_ledger()
    {
        [$cs, $enrollment, $room] = $this->activeClassWithStudent();
        $att = app(AttendanceService::class)->markAttendance($this->storePayload($cs, $enrollment, $room));
        $this->assertSame(7, $enrollment->fresh()->remaining_meetings);

        $this->actingAs($this->admin)
            ->delete(route('admin.attendance.destroy', $att->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('attendance', ['id' => $att->id]);
        $this->assertSame(8, $enrollment->fresh()->remaining_meetings, 'sisa pertemuan balik setelah reverse');
    }

    // ── TUTOR ────────────────────────────────────────────────────────────

    #[Test]
    public function tutor_can_view_own_attendance()
    {
        $this->actingAs($this->tutorUser)->get(route('tutor.attendance.index'))->assertOk();
    }

    #[Test]
    public function tutor_can_create_attendance_and_it_reduces_remaining_meetings()
    {
        [$cs, $enrollment, $room] = $this->activeClassWithStudent(5, 500_000);

        $this->actingAs($this->tutorUser)
            ->postJson(route('tutor.attendance.store'), $this->storePayload($cs, $enrollment, $room))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(4, $enrollment->fresh()->remaining_meetings);
        $this->assertDatabaseHas('attendance_student', ['enrollment_id' => $enrollment->id, 'is_present' => true]);
    }

    #[Test]
    public function creating_attendance_posts_a_revenue_recognition_journal()
    {
        [$cs, $enrollment, $room] = $this->activeClassWithStudent(8, 800_000);

        $this->actingAs($this->tutorUser)
            ->postJson(route('tutor.attendance.store'), $this->storePayload($cs, $enrollment, $room));

        // 800.000 / 8 pertemuan = 100.000 per pertemuan
        $this->assertDatabaseHas('journals', [
            'type' => 'revenue_recognition',
            'enrollment_id' => $enrollment->id,
            'total_amount' => 100_000,
        ]);
    }

    #[Test]
    public function marking_the_same_session_twice_does_not_double_decrement()
    {
        [$cs, $enrollment, $room] = $this->activeClassWithStudent(5, 500_000);
        $payload = $this->storePayload($cs, $enrollment, $room);

        $this->actingAs($this->tutorUser)->postJson(route('tutor.attendance.store'), $payload)->assertOk();
        $this->actingAs($this->tutorUser)->postJson(route('tutor.attendance.store'), $payload)->assertOk();

        $this->assertSame(4, $enrollment->fresh()->remaining_meetings, 'tetap turun 1, bukan 2');
        $this->assertSame(1, Attendance::where('class_session_id', $cs->id)->count());
    }

    #[Test]
    public function tutor_cannot_mark_attendance_for_a_class_they_do_not_teach()
    {
        $program = Program::factory()->create(['min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $enrollment = Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        $room = Classroom::factory()->create();

        $this->actingAs($this->tutorUser)
            ->postJson(route('tutor.attendance.store'), $this->storePayload($cs, $enrollment, $room))
            ->assertForbidden();
    }

    #[Test]
    public function tutor_can_delete_own_attendance()
    {
        [$cs, $enrollment, $room] = $this->activeClassWithStudent();
        $att = app(AttendanceService::class)->markAttendance($this->storePayload($cs, $enrollment, $room));

        $this->actingAs($this->tutorUser)
            ->deleteJson(route('tutor.attendance.destroy', $att->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('attendance', ['id' => $att->id]);
    }

    #[Test]
    public function student_cannot_access_tutor_attendance_routes()
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('tutor.attendance.index'))->assertForbidden();
    }
}
