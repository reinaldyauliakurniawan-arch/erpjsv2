<?php

namespace App\Support;

use App\Enums\DayOfWeek;
use App\Models\Enrollment;
use App\Models\RoomBooking;
use App\Models\Schedule;
use Carbon\Carbon;

/**
 * Menghitung pertemuan mendatang untuk sebuah enrollment dari jadwal kelasnya,
 * dengan memperhitungkan "skip" dan "pindah ruang" (room_bookings).
 *
 * Satu sumber kebenaran supaya dashboard siswa dan halaman enrollment admin
 * menampilkan hal yang sama.
 *
 * Tiap entri: [
 *   'date'       => 'YYYY-MM-DD',
 *   'date_label' => '6 Jul 2026',
 *   'day'        => 'Senin',
 *   'time_block' => '09:00-10:30',
 *   'classroom'  => 'Harvard',        // ruang efektif (ruang pengganti kalau pindah)
 *   'is_today'   => bool,
 *   'status'     => 'ok' | 'skipped' | 'moved',
 *   'moved_to'   => 'Stanford' | null,
 * ]
 */
class UpcomingSessions
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function forEnrollment(Enrollment $enrollment, int $limit = 4, int $lookaheadDays = 28): array
    {
        if (! $enrollment->class_session_id) {
            return [];
        }

        $schedules = $enrollment->relationLoaded('schedules')
            ? $enrollment->schedules
            : $enrollment->schedules()->with('classroom')->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $today = Carbon::today();
        $scheduleIds = Schedule::where('class_session_id', $enrollment->class_session_id)->pluck('id');

        $bookings = RoomBooking::with('classroom')
            ->whereDate('date', '>=', $today->toDateString())
            ->whereDate('date', '<=', $today->copy()->addDays($lookaheadDays)->toDateString())
            ->where(fn ($q) => $q
                ->where('class_session_id', $enrollment->class_session_id)
                ->orWhereIn('schedule_id', $scheduleIds)
                ->orWhereIn('classroom_id', $schedules->pluck('classroom_id')->filter()))
            ->get();

        $onDate = fn ($b, Carbon $date) => Carbon::parse($b->date)->toDateString() === $date->toDateString();

        $out = [];
        for ($i = 0; $i <= $lookaheadDays && count($out) < $limit; $i++) {
            $date = $today->copy()->addDays($i);
            $slot = $schedules->firstWhere('day', DayOfWeek::fromDate($date)->value);
            if (! $slot) {
                continue;
            }

            $entry = [
                'date' => $date->toDateString(),
                'date_label' => $date->isoFormat('D MMM YYYY'),
                'day' => $slot->day,
                'time_block' => $slot->time_block,
                'classroom' => $slot->classroom?->name ?? '—',
                'is_today' => $i === 0,
                'status' => 'ok',
                'moved_to' => null,
            ];

            $move = $bookings->first(fn ($b) => $b->type === 'temporary'
                && (int) $b->class_session_id === (int) $enrollment->class_session_id
                && $b->time_block === $slot->time_block
                && $onDate($b, $date));
            if ($move) {
                $entry['status'] = 'moved';
                $entry['moved_to'] = $move->classroom?->name ?? '—';
                $entry['classroom'] = $entry['moved_to'];
                $out[] = $entry;

                continue;
            }

            $skipped = $bookings->contains(fn ($b) => $b->type === 'regular_skip'
                && (int) $b->classroom_id === (int) $slot->classroom_id
                && $b->time_block === $slot->time_block
                && $onDate($b, $date));
            if ($skipped) {
                $entry['status'] = 'skipped';
            }

            $out[] = $entry;
        }

        return $out;
    }
}
