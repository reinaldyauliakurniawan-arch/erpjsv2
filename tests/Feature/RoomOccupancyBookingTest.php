<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ClassroomController;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\RoomBooking;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Occupancy ruang: booking sementara menambah slot terpakai, skip jadwal
 * reguler mengurangi, ruang online tidak dihitung sama sekali.
 */
class RoomOccupancyBookingTest extends TestCase
{
    use RefreshDatabase;

    private function stats(string $date): array
    {
        return collect(app(ClassroomController::class)->buildOccupancyStats(
            Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()
        ))->keyBy('name')->all();
    }

    #[Test]
    public function temporary_booking_counts_as_occupied_and_skip_frees_the_slot(): void
    {
        $room = Classroom::factory()->create(['name' => 'Harvard', 'kind' => 'physical']);
        $date = '2026-11-03'; // Selasa
        $cs = ClassSession::factory()->create(['status' => 'active']);
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id, 'day' => 'Selasa', 'time_block' => '16:00-17:30']);

        $this->assertSame(1, $this->stats($date)['Harvard']['occupied']);

        RoomBooking::create(['classroom_id' => $room->id, 'date' => $date, 'time_block' => '10:30-12:00', 'type' => 'temporary', 'notes' => 'Bloomly']);
        $this->assertSame(2, $this->stats($date)['Harvard']['occupied']);

        RoomBooking::create(['classroom_id' => $room->id, 'date' => $date, 'time_block' => '16:00-17:30', 'type' => 'regular_skip']);
        $this->assertSame(1, $this->stats($date)['Harvard']['occupied']);

        // booking di tanggal lain tidak ikut
        RoomBooking::create(['classroom_id' => $room->id, 'date' => '2026-11-04', 'time_block' => '09:00-10:30', 'type' => 'temporary']);
        $this->assertSame(1, $this->stats($date)['Harvard']['occupied']);
    }

    #[Test]
    public function online_room_is_excluded_from_occupancy(): void
    {
        Classroom::factory()->create(['name' => 'Harvard', 'kind' => 'physical']);
        $online = Classroom::factory()->create(['name' => 'Online', 'kind' => 'online']);
        RoomBooking::create(['classroom_id' => $online->id, 'date' => '2026-11-03', 'time_block' => '18:30-20:00', 'type' => 'temporary']);

        $stats = $this->stats('2026-11-03');
        $this->assertArrayNotHasKey('Online', $stats);
        $this->assertSame(0, $stats['Harvard']['occupied']);
    }
}
