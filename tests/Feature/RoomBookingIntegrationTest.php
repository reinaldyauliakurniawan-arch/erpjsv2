<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integrasi mutlak booking ruangan antara peran admin dan tutor:
 * apa pun yang dibuat/dihapus di satu peran harus tampil & (kalau relevan)
 * memicu notifikasi ke peran lainnya.
 */
class RoomBookingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function tutorUser(): array
    {
        $user = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $user->id]);

        return [$user, $tutor];
    }

    /** Kelas reguler milik $tutor, terjadwal di ruang $room pada hari tanggal $date. */
    private function scheduledClass(Tutor $tutor, Classroom $room, string $date, string $block = '09:00-10:30'): Schedule
    {
        $day = DayOfWeek::fromDate($date)->value;
        $cs = ClassSession::factory()->create(['status' => 'active', 'name' => 'Kelas '.fake()->word()]);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);

        return Schedule::factory()->create([
            'class_session_id' => $cs->id,
            'classroom_id' => $room->id,
            'day' => $day,
            'time_block' => $block,
        ]);
    }

    #[Test]
    public function tutor_skip_creates_a_regular_skip_and_notifies_admin(): void
    {
        $admin = $this->admin();
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $date = now()->next(Carbon::MONDAY)->toDateString();
        $schedule = $this->scheduledClass($tutor, $room, $date);

        $this->actingAs($tutorU)->post(route('tutor.room-bookings.store'), [
            'type' => 'regular_skip',
            'classroom_id' => $room->id,
            'schedule_id' => $schedule->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
            'notes' => 'sakit',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            RoomBooking::where('classroom_id', $room->id)
                ->whereDate('date', $date)
                ->where('type', 'regular_skip')
                ->where('tutor_id', $tutor->id)
                ->exists(),
        );
        $this->assertSame('session_skipped', $admin->fresh()->notifications()->first()?->data['type']);
    }

    #[Test]
    public function tutor_cannot_skip_a_class_they_do_not_teach(): void
    {
        [$tutorU, $tutor] = $this->tutorUser();
        [, $otherTutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $date = now()->next(Carbon::MONDAY)->toDateString();
        $schedule = $this->scheduledClass($otherTutor, $room, $date);

        $this->actingAs($tutorU)->post(route('tutor.room-bookings.store'), [
            'type' => 'regular_skip',
            'classroom_id' => $room->id,
            'schedule_id' => $schedule->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('room_bookings', 0);
    }

    #[Test]
    public function tutor_booking_is_visible_on_the_admin_schedule_and_vice_versa(): void
    {
        $admin = $this->admin();
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create(['name' => 'Harvard']);
        $date = now()->next(Carbon::MONDAY)->toDateString();

        // Tutor books an empty room.
        $this->actingAs($tutorU)->post(route('tutor.room-bookings.store'), [
            'classroom_id' => $room->id,
            'date' => $date,
            'time_block' => '13:00-14:30',
            'notes' => 'trial class',
        ])->assertSessionHasNoErrors();

        // Admin sees it (with the tutor's name).
        $this->actingAs($admin)->get(route('admin.schedule.index'))
            ->assertOk()
            ->assertSee($tutorU->name);

        // Admin books another slot; tutor sees "Admin" on their schedule.
        $this->actingAs($admin)->post(route('admin.room-bookings.store'), [
            'classroom_id' => $room->id,
            'date' => $date,
            'time_block' => '16:00-17:30',
            'type' => 'temporary',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('room_bookings', ['classroom_id' => $room->id, 'time_block' => '16:00-17:30']);
        $this->actingAs($tutorU)->get(route('tutor.schedule.index'))->assertOk();
    }

    #[Test]
    public function admin_skip_of_a_class_notifies_its_tutors(): void
    {
        $admin = $this->admin();
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $date = now()->next(Carbon::MONDAY)->toDateString();
        $schedule = $this->scheduledClass($tutor, $room, $date);

        $this->actingAs($admin)->post(route('admin.room-bookings.store'), [
            'classroom_id' => $room->id,
            'schedule_id' => $schedule->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
            'type' => 'regular_skip',
        ])->assertSessionHasNoErrors();

        $this->assertSame('session_skipped', $tutorU->fresh()->notifications()->first()?->data['type']);
    }

    #[Test]
    public function admin_deleting_a_tutor_booking_notifies_the_tutor(): void
    {
        $admin = $this->admin();
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $booking = RoomBooking::create([
            'classroom_id' => $room->id,
            'date' => now()->next(Carbon::MONDAY)->toDateString(),
            'time_block' => '09:00-10:30',
            'type' => 'temporary',
            'tutor_id' => $tutor->id,
            'notes' => 'Booked by tutor: '.$tutorU->name,
        ]);

        $this->actingAs($admin)->delete(route('admin.room-bookings.destroy', $booking->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('room_bookings', ['id' => $booking->id]);
        $this->assertSame('room_booking_removed', $tutorU->fresh()->notifications()->first()?->data['type']);
    }

    #[Test]
    public function tutor_can_cancel_their_own_skip_and_admin_is_notified(): void
    {
        $admin = $this->admin();
        [$tutorU, $tutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $date = now()->next(Carbon::MONDAY)->toDateString();
        $schedule = $this->scheduledClass($tutor, $room, $date);

        $skip = RoomBooking::create([
            'classroom_id' => $room->id,
            'schedule_id' => $schedule->id,
            'date' => $date,
            'time_block' => '09:00-10:30',
            'type' => 'regular_skip',
            'tutor_id' => $tutor->id,
        ]);

        $this->actingAs($tutorU)->delete(route('tutor.room-bookings.destroy', $skip->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('room_bookings', ['id' => $skip->id]);
        $this->assertSame('room_booking_cancelled', $admin->fresh()->notifications()->first()?->data['type']);
    }

    #[Test]
    public function tutor_cannot_delete_another_tutors_booking(): void
    {
        [$tutorU] = $this->tutorUser();
        [, $otherTutor] = $this->tutorUser();
        $room = Classroom::factory()->create();
        $booking = RoomBooking::create([
            'classroom_id' => $room->id,
            'date' => now()->next(Carbon::MONDAY)->toDateString(),
            'time_block' => '09:00-10:30',
            'type' => 'temporary',
            'tutor_id' => $otherTutor->id,
        ]);

        $this->actingAs($tutorU)->delete(route('tutor.room-bookings.destroy', $booking->id))
            ->assertNotFound();

        $this->assertDatabaseHas('room_bookings', ['id' => $booking->id]);
    }
}
