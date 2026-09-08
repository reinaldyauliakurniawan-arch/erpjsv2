<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\User;
use App\Services\TutorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jadwal di dashboard admin dan dashboard tutor harus konsisten satu sama lain
 * dan dengan halaman Jadwal: kelas yang jalan hari ini, dan apakah di-skip.
 */
class DashboardScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Tutor, 2: ClassSession, 3: Schedule}
     */
    private function classWithTutorToday(): array
    {
        $tutorU = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorU->id]);
        $program = Program::factory()->create(['min_quota' => 1]);
        $room = Classroom::factory()->create(['name' => 'Harvard']);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active', 'name' => 'Kelas Uji']);

        Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        app(TutorAssignmentService::class)->assignToClassSession($cs, $tutor->id, 'confirmed');

        $schedule = Schedule::factory()->create([
            'class_session_id' => $cs->id,
            'classroom_id' => $room->id,
            'day' => DayOfWeek::fromDate(now())->value,
            'time_block' => '09:00-10:30',
        ]);

        return [$tutorU, $tutor, $cs, $schedule];
    }

    #[Test]
    public function tutor_dashboard_shows_todays_session_and_active_class_only(): void
    {
        [$tutorU, $tutor, $cs] = $this->classWithTutorToday();

        // Kelas kedua: satu-satunya siswanya sudah lulus -> tidak boleh muncul
        // di "Kelas Aktif" walau pivot class_session_tutor masih ada.
        $graduated = ClassSession::factory()->create(['status' => 'active', 'name' => 'Kelas Lulus']);
        Enrollment::factory()->create(['class_session_id' => $graduated->id, 'status' => 'graduate']);
        $graduated->tutors()->attach($tutor->id, ['status' => 'confirmed']);

        $res = $this->actingAs($tutorU)->get(route('tutor.dashboard'))->assertOk();
        $res->assertSee('Kelas Uji');
        $res->assertSee('Sesi Hari Ini');
        $res->assertDontSee('Kelas Lulus');
    }

    #[Test]
    public function a_skip_today_is_reflected_the_same_on_both_dashboards(): void
    {
        [$tutorU, $tutor, $cs, $schedule] = $this->classWithTutorToday();
        $admin = User::factory()->create(['role' => 'admin']);

        // Belum di-skip -> kedua dashboard bilang "Jalan".
        $this->actingAs($tutorU)->get(route('tutor.dashboard'))->assertOk()->assertSee('Jalan');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Jalan');

        RoomBooking::create([
            'classroom_id' => $schedule->classroom_id,
            'schedule_id' => $schedule->id,
            'date' => now()->toDateString(),
            'time_block' => '09:00-10:30',
            'type' => 'regular_skip',
            'tutor_id' => $tutor->id,
        ]);

        // Setelah di-skip -> kedua dashboard bilang "Di-skip".
        $this->actingAs($tutorU)->get(route('tutor.dashboard'))->assertOk()->assertSee('Di-skip');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Di-skip');
    }

    #[Test]
    public function admin_dashboard_today_sessions_ignores_inactive_class_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Classroom::factory()->create();
        $inactive = ClassSession::factory()->create(['status' => 'inactive', 'name' => 'Kelas Nonaktif']);
        Schedule::factory()->create([
            'class_session_id' => $inactive->id,
            'classroom_id' => $room->id,
            'day' => DayOfWeek::fromDate(now())->value,
            'time_block' => '13:00-14:30',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Kelas Nonaktif');
    }
}
