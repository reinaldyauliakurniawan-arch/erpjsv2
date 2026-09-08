<?php

namespace Tests\Feature;

use App\Enums\ClassroomKind;
use App\Http\Controllers\Admin\ClassroomController;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Okupansi ruangan hanya menghitung ruang FISIK. Kelas online (bisa di mana
 * saja) dan kelas di luar Just Speak / B2B tetap tercatat & tampil di jadwal,
 * tapi tidak menaikkan/menurunkan angka okupansi.
 */
class RoomOccupancyTest extends TestCase
{
    use RefreshDatabase;

    private function occupancy(): array
    {
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $to = $from->copy()->endOfWeek();
        $stats = app(ClassroomController::class)->buildOccupancyStats($from, $to);

        return [
            'occupied' => array_sum(array_column($stats, 'occupied')),
            'total' => array_sum(array_column($stats, 'total')),
            'rooms' => array_column($stats, 'name'),
        ];
    }

    #[Test]
    public function online_and_offsite_rooms_are_excluded_from_occupancy(): void
    {
        $physical = Classroom::factory()->create(['kind' => ClassroomKind::PHYSICAL->value, 'name' => 'Harvard']);
        $online = Classroom::factory()->online()->create();
        $offsite = Classroom::factory()->offsite()->create(['name' => 'Kantor Klien']);

        // Satu kelas di tiap ruang, hari & jam sama.
        foreach ([$physical, $online, $offsite] as $room) {
            $cs = ClassSession::factory()->create(['status' => 'active']);
            Schedule::factory()->create([
                'class_session_id' => $cs->id,
                'classroom_id' => $room->id,
                'day' => 'Senin',
                'time_block' => '09:00-10:30',
            ]);
        }

        $o = $this->occupancy();

        // Hanya ruang fisik yang masuk daftar & hitungan.
        $this->assertSame(['Harvard'], $o['rooms']);
        $this->assertSame(1, $o['occupied'], 'hanya kelas di ruang fisik yang dihitung');
        $this->assertSame(6 * 7, $o['total'], '6 blok jam x 7 hari untuk 1 ruang fisik');
    }

    #[Test]
    public function is_at_just_speak_is_derived_from_kind(): void
    {
        $online = Classroom::factory()->online()->create();
        $offsite = Classroom::factory()->offsite()->create();
        $physical = Classroom::factory()->create(['kind' => ClassroomKind::PHYSICAL->value]);

        $this->assertFalse($online->fresh()->is_at_just_speak);
        $this->assertFalse($offsite->fresh()->is_at_just_speak);
        $this->assertTrue($physical->fresh()->is_at_just_speak);
    }

    #[Test]
    public function online_class_has_no_room_capacity_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seed(ChartOfAccountsSeeder::class);

        $program = Program::factory()->create([
            'type' => 'group', 'price' => 500_000, 'total_meetings' => 10, 'min_quota' => 1,
        ]);
        $online = Classroom::factory()->online()->create(['capacity' => 1]);
        $session = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        Schedule::factory()->create([
            'class_session_id' => $session->id,
            'classroom_id' => $online->id,
            'day' => 'Senin',
            'time_block' => '09:00-10:30',
        ]);
        // Ruang online "penuh" (kapasitas 1) sudah punya 1 siswa.
        Enrollment::factory()->create(['class_session_id' => $session->id, 'status' => 'active']);

        // Siswa ke-2 tetap bisa masuk — online tidak dibatasi kapasitas ruang.
        $enrollment = Enrollment::factory()->create(['program_id' => $program->id, 'status' => 'active', 'class_session_id' => null]);
        $this->actingAs($admin)
            ->post(route('admin.class-sessions.assign', $session->id), ['enrollment_id' => $enrollment->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($session->id, $enrollment->fresh()->class_session_id);
    }
}
