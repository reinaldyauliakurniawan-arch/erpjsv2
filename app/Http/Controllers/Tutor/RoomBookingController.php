<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Services\Notifier;
use App\Support\ScheduleFormat;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoomBookingController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function store(Request $request)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'date' => 'required|date',
            'time_block' => 'required|string',
            'type' => 'nullable|in:temporary,regular_skip',
            'schedule_id' => 'nullable|exists:schedules,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $request->merge(['time_block' => ScheduleFormat::timeBlock($request->time_block)]);

        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
        $type = $request->input('type', 'temporary');

        // Cegah booking masa lampau
        $endTime = explode('-', $request->time_block)[1] ?? '23:59';
        $slotEnd = Carbon::parse($request->date.' '.trim($endTime));
        if ($slotEnd->isPast()) {
            return back()->with('error', 'Slot ini sudah lewat dan tidak bisa diubah.');
        }

        return $type === 'regular_skip'
            ? $this->skip($request, $tutor)
            : $this->book($request, $tutor);
    }

    /** Tutor menandai satu pertemuan reguler (kelas yang ia ajar) sebagai tidak jalan. */
    private function skip(Request $request, Tutor $tutor)
    {
        $schedule = Schedule::with(['classSession', 'classroom'])
            ->when($request->schedule_id, fn ($q) => $q->where('id', $request->schedule_id))
            ->where('classroom_id', $request->classroom_id)
            ->where('time_block', $request->time_block)
            ->where('day', DayOfWeek::fromDate($request->date)->value)
            ->whereNotNull('class_session_id')
            ->first();

        // Tutor hanya boleh skip pertemuan kelas yang benar-benar ia ajar.
        if (! $schedule || ! $schedule->classSession
            || ! $schedule->classSession->tutors()->where('tutor_id', $tutor->id)->exists()) {
            return back()->with('error', 'Kamu hanya bisa skip pertemuan kelas yang kamu ajar.');
        }

        $already = RoomBooking::where('classroom_id', $request->classroom_id)
            ->whereDate('date', $request->date)
            ->where('time_block', $request->time_block)
            ->where('type', 'regular_skip')
            ->exists();
        if ($already) {
            return back()->with('error', 'Pertemuan ini sudah di-skip.');
        }

        $reason = $request->notes ? 'Skip oleh '.Auth::user()->name.': '.$request->notes
                                  : 'Skip oleh '.Auth::user()->name;

        try {
            RoomBooking::create([
                'classroom_id' => $request->classroom_id,
                'schedule_id' => $schedule->id,
                'date' => $request->date,
                'time_block' => $request->time_block,
                'type' => 'regular_skip',
                'tutor_id' => $tutor->id,
                'notes' => $reason,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return back()->with('error', 'Pertemuan ini baru saja di-skip oleh orang lain.');
        }

        $this->notifier->sessionSkippedByTutor(
            $tutor, $schedule->classSession->name, $schedule->classroom?->name ?? 'ruang',
            $request->date, $request->time_block, $request->notes
        );

        return back()->with('success', 'Pertemuan di-skip. Admin sudah diberi tahu.');
    }

    /** Tutor mem-booking ruang yang kosong (atau slot reguler yang sudah di-skip). */
    private function book(Request $request, Tutor $tutor)
    {
        $conflict = RoomBooking::where('classroom_id', $request->classroom_id)
            ->whereDate('date', $request->date)
            ->where('time_block', $request->time_block)
            ->where('type', 'temporary')
            ->exists();
        if ($conflict) {
            return back()->with('error', 'Slot ini sudah dibooking.');
        }

        // Kelas reguler aktif hanya boleh di-booking-i kalau sudah di-skip.
        $hasActiveRegular = Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', DayOfWeek::fromDate($request->date)->value)
            ->where('time_block', $request->time_block)
            ->whereHas('classSession', fn ($q) => $q->where('status', 'active'))
            ->exists();
        if ($hasActiveRegular) {
            $isSkipped = RoomBooking::where('classroom_id', $request->classroom_id)
                ->whereDate('date', $request->date)
                ->where('time_block', $request->time_block)
                ->where('type', 'regular_skip')
                ->exists();
            if (! $isSkipped) {
                return back()->with('error', 'Slot ini sedang dipakai kelas reguler. Skip jadwal reguler terlebih dahulu sebelum booking.');
            }
        }

        $notes = 'Booked by tutor: '.Auth::user()->name.($request->notes ? ' — '.$request->notes : '');

        // "Pindah ruang": kalau tutor punya kelas di slot jam/hari ini yang
        // pertemuannya sudah di-skip pada tanggal ini, tautkan booking baru
        // ke kelas itu supaya siswa lihat "kelas dipindah ke <ruang>".
        $day = DayOfWeek::fromDate($request->date)->value;
        $movedClassSessionId = Schedule::query()
            ->where('day', $day)
            ->where('time_block', $request->time_block)
            ->whereHas('classSession.tutors', fn ($q) => $q->where('tutor_id', $tutor->id))
            ->whereExists(fn ($q) => $q->from('room_bookings')
                ->whereColumn('room_bookings.schedule_id', 'schedules.id')
                ->where('room_bookings.type', 'regular_skip')
                ->whereDate('room_bookings.date', $request->date))
            ->value('class_session_id');

        try {
            RoomBooking::create([
                'classroom_id' => $request->classroom_id,
                'class_session_id' => $movedClassSessionId,
                'date' => $request->date,
                'time_block' => $request->time_block,
                'type' => 'temporary',
                'tutor_id' => $tutor->id,
                'notes' => $notes,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return back()->with('error', 'Slot ini baru saja dibooking oleh orang lain.');
        }

        $classroomName = Classroom::whereKey($request->classroom_id)->value('name') ?? 'ruang';
        $this->notifier->roomBookedByTutor($tutor, $classroomName, $request->date, $request->time_block, $request->notes);

        return back()->with('success', 'Slot berhasil dibooking. Admin sudah diberi tahu.');
    }

    public function destroy($id)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        // Tutor boleh membatalkan booking / skip yang ia buat sendiri.
        $booking = RoomBooking::with('classroom')
            ->where('id', $id)
            ->where('tutor_id', $tutor->id)
            ->firstOrFail();

        $kindLabel = $booking->type === 'regular_skip' ? 'skip' : 'booking';
        $room = $booking->classroom?->name ?? 'ruang';
        $date = $booking->date?->toDateString() ?? (string) $booking->date;
        $block = $booking->time_block;

        DB::transaction(fn () => $booking->delete());

        $this->notifier->roomBookingCancelledByTutor($tutor, $kindLabel, $room, $date, $block);

        return back()->with('success', ucfirst($kindLabel).' dibatalkan. Admin sudah diberi tahu.');
    }
}
