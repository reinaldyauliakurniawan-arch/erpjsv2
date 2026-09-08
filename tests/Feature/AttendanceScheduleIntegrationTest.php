<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman absensi harus nyambung dengan jadwal:
 *  - form absensi tutor auto-isi jam & ruang dari jadwal kelas, dan
 *    memperingatkan kalau tanggal itu di-skip / dipindah;
 *  - absensi yang SUDAH tercatat tidak bisa ditandai "skip/ditunda" (pertemuan
 *    yang sudah jalan tidak bisa dibatalkan lewat ganti status) — koreksi lewat
 *    reverse/hapus absensi.
 */
class AttendanceScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function tutorUser(): array
    {
        $u = User::factory()->create(['role' => 'tutor']);

        return [$u, Tutor::factory()->create(['user_id' => $u->id])];
    }

    #[Test]
    public function tutor_session_search_returns_the_class_scheduled_slot(): void
    {
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create(['name' => 'Harvard']);
        $program = Program::factory()->create(['name' => 'TOEFL Prep']);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active', 'name' => 'TOEFL A']);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        Schedule::factory()->create([
            'class_session_id' => $cs->id,
            'classroom_id' => $room->id,
            'day' => 'Senin',
            'time_block' => '13:00-14:30',
        ]);

        $res = $this->actingAs($tutorU)
            ->getJson(route('tutor.attendance.search-sessions', ['q' => 'TOEFL', 'mode' => 'own']))
            ->assertOk()
            ->json();

        $this->assertSame('13:00-14:30', $res[0]['default_time_block']);
        $this->assertSame($room->id, $res[0]['default_classroom_id']);
        $this->assertSame('Senin', $res[0]['scheduled_slots'][0]['day']);
    }

    #[Test]
    public function tutor_history_reports_a_skip_for_the_session(): void
    {
        foreach (['1001', '1002', '1003', '2002', '2003', '4101', '5001'] as $c) {
            Account::factory()->create(['code' => $c]);
        }
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $cs = ClassSession::factory()->create(['status' => 'active']);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        Enrollment::factory()->create(['class_session_id' => $cs->id, 'status' => 'active']);
        $schedule = Schedule::factory()->create([
            'class_session_id' => $cs->id, 'classroom_id' => $room->id,
            'day' => 'Senin', 'time_block' => '09:00-10:30',
        ]);
        RoomBooking::create([
            'classroom_id' => $room->id, 'schedule_id' => $schedule->id,
            'class_session_id' => $cs->id, 'date' => '2026-07-06',
            'time_block' => '09:00-10:30', 'type' => 'regular_skip', 'tutor_id' => $tutor->id,
        ]);

        $res = $this->actingAs($tutorU)
            ->getJson(route('tutor.attendance.history', ['class_session_id' => $cs->id]))
            ->assertOk()
            ->json();

        $this->assertContains('regular_skip', collect($res['room_bookings'])->pluck('type')->all());
    }

    #[Test]
    public function recorded_attendance_cannot_be_marked_skipped_or_postponed(): void
    {
        foreach (['1001', '1002', '1003', '2002', '2003', '4101', '5001'] as $c) {
            Account::factory()->create(['code' => $c]);
        }
        $admin = User::factory()->create(['role' => 'admin']);
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $program = Program::factory()->create(['total_meetings' => 8, 'price' => 800_000, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active',
            'payment_method' => 'full upfront', 'payment_status' => 'full', 'remaining_meetings' => 8,
        ]);
        $date = now()->next(DayOfWeek::MONDAY->name)->toDateString();
        Schedule::factory()->create([
            'class_session_id' => $cs->id, 'classroom_id' => $room->id,
            'day' => DayOfWeek::fromDate($date)->value, 'time_block' => '09:00-10:30',
        ]);

        $attendance = app(AttendanceService::class)->markAttendance([
            'class_session_id' => $cs->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
            'classroom_id' => $room->id,
            'marked_by' => $tutorU->id,
            'students' => [['enrollment_id' => $enrollment->id, 'is_present' => true]],
        ]);

        // "skipped" / "postponed" ditolak — pertemuan yang sudah tercatat tidak
        // bisa dibatalkan lewat ganti status.
        $this->actingAs($admin)->patch(route('admin.attendance.update', $attendance->id), ['status' => 'skipped'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('admin.attendance.update', $attendance->id), ['status' => 'postponed'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('room_bookings', [
            'class_session_id' => $cs->id, 'time_block' => '09:00-10:30', 'type' => 'regular_skip',
        ]);

        // Status yang valid tetap bisa di-set, tanpa efek ke jadwal.
        $this->actingAs($admin)->patch(route('admin.attendance.update', $attendance->id), ['status' => 'finished'])
            ->assertRedirect();
        $this->assertDatabaseHas('attendance', ['id' => $attendance->id, 'status' => 'finished']);
        $this->assertDatabaseMissing('room_bookings', [
            'class_session_id' => $cs->id, 'time_block' => '09:00-10:30', 'type' => 'regular_skip',
        ]);
    }
}
