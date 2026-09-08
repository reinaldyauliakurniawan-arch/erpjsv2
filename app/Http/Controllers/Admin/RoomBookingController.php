<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Services\Notifier;
use App\Support\ScheduleFormat;
use Illuminate\Http\Request;

class RoomBookingController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function store(Request $request)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'date'         => 'required|date',
            'time_block'   => 'required|string',
            'type'         => 'required|in:regular_skip,temporary',
            'enrollment_id'=> 'nullable|exists:enrollments,id',
            'tutor_id'     => 'nullable|exists:tutors,id',
            'notes'        => 'nullable|string|max:255',
            'schedule_id'   => 'nullable|exists:schedules,id',
            'class_session_id' => 'nullable|exists:class_sessions,id',
        ]);

        $request->merge(['time_block' => ScheduleFormat::timeBlock($request->time_block)]);

        // Cegah booking masa lampau (per time block)
        $endTime = explode('-', $request->time_block)[1] ?? '23:59';
        $slotEnd = \Carbon\Carbon::parse($request->date . ' ' . trim($endTime));
        if ($slotEnd->isPast()) {
            return back()->withErrors(['error' => 'Slot ini sudah lewat dan tidak bisa diubah.']);
        }
        // Cegah duplikat
        $exists = RoomBooking::where('classroom_id', $request->classroom_id)
            ->whereDate('date', $request->date)
            ->where('time_block', $request->time_block)
            ->where('type', $request->type)
            ->exists();

        // Boleh temporary booking di slot yang sudah di-skip
        if ($request->type === 'temporary') {
            $exists = RoomBooking::where('classroom_id', $request->classroom_id)
                ->whereDate('date', $request->date)
                ->where('time_block', $request->time_block)
                ->where('type', 'temporary')
                ->exists();
        }

        if ($exists) {
            return back()->withErrors(['error' => 'Booking ini sudah ada.']);
        }

        // Kalau mau booking temporary, pastikan tidak bentrok dengan kelas reguler aktif yang belum di-skip
        if ($request->type === 'temporary') {
            $dayName = DayOfWeek::fromDate($request->date)->value; // "Senin", dst
            $hasActiveRegularSchedule = Schedule::where('classroom_id', $request->classroom_id)
                ->where('day', $dayName)
                ->where('time_block', $request->time_block)
                ->whereHas('classSession', fn($q) => $q->where('status', 'active'))
                ->exists();

            if ($hasActiveRegularSchedule) {
                $isSkipped = RoomBooking::where('classroom_id', $request->classroom_id)
                    ->whereDate('date', $request->date)
                    ->where('time_block', $request->time_block)
                    ->where('type', 'regular_skip')
                    ->exists();

                if (!$isSkipped) {
                    return back()->withErrors(['error' => 'Slot ini sedang dipakai kelas reguler. Skip jadwal reguler terlebih dahulu sebelum booking.']);
                }
            }
        }

        try {
            $booking = RoomBooking::create($request->only(
                'classroom_id', 'schedule_id', 'class_session_id', 'date', 'time_block', 'type', 'enrollment_id', 'tutor_id', 'notes'
            ));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return back()->withErrors(['error' => 'Slot ini baru saja dibooking oleh orang lain.']);
        }

        $this->notifyAffectedTutors($booking);

        return back()->with('success', 'Booking berhasil disimpan.');
    }

    public function destroy($id)
    {
        $booking = RoomBooking::with('classroom')->findOrFail($id);
        $date = $booking->date?->toDateString() ?? (string) $booking->date;
        $room = $booking->classroom?->name ?? 'ruang';

        if ($booking->type === 'regular_skip') {
            // Skip dibatalkan -> kelas dianggap jalan lagi. Beri tahu tutor kelas itu.
            $cs = $this->classSessionFor($booking, $date);
            if ($cs) {
                $this->notifier->toClassTutors($cs, 'session_skipped',
                    'Skip dibatalkan — kelas jalan lagi',
                    "Skip pada {$date} {$booking->time_block} untuk kelas {$cs->name} dibatalkan admin.",
                    route('tutor.schedule.index'));
            }
        } elseif ($booking->tutor_id) {
            // Booking milik tutor dihapus admin.
            $this->notifier->roomBookingRemovedByAdmin($booking->tutor_id, 'booking', $room, $date, $booking->time_block);
        }

        $booking->delete();

        return back()->with('success', 'Booking dihapus.');
    }

    private function classSessionFor(RoomBooking $booking, string $date): ?ClassSession
    {
        if ($booking->schedule_id) {
            return Schedule::with('classSession')->find($booking->schedule_id)?->classSession;
        }

        return ClassSession::whereHas('schedules', fn ($q) => $q
            ->where('classroom_id', $booking->classroom_id)
            ->where('time_block', $booking->time_block)
            ->where('day', DayOfWeek::fromDate($date)->value))->first();
    }

    /** Aksi admin yang berdampak ke tutor -> kirim notifikasi supaya sinkron. */
    private function notifyAffectedTutors(RoomBooking $booking): void
    {
        $date = $booking->date?->toDateString() ?? (string) $booking->date;

        if ($booking->type === 'regular_skip') {
            $cs = $this->classSessionFor($booking, $date);
            if ($cs) {
                $this->notifier->sessionSkippedByAdmin($cs, $date, $booking->time_block);
            }

            return;
        }

        if ($booking->tutor_id) {
            $room = $booking->classroom?->name ?? \App\Models\Classroom::whereKey($booking->classroom_id)->value('name') ?? 'ruang';
            $this->notifier->roomBookedForTutorByAdmin($booking->tutor_id, $room, $date, $booking->time_block);
        }
    }
}
