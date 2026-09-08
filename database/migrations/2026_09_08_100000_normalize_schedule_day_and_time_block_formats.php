<?php

use App\Support\ScheduleFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan format `day` dan `time_block` di `schedules` dan
 * `tutor_availability` ke bentuk baku (lihat App\Support\ScheduleFormat):
 * hari Bahasa Indonesia kapital ("Senin"), jam pakai titik tanpa spasi
 * ("09.00-10.30").
 *
 * Sebelum ini isinya campur ("Monday" vs "Senin", "09:00-10:30" vs
 * "09.00 - 10.30") sehingga slot di `schedules` tidak pernah cocok dengan
 * slot di `tutor_availability` — akibatnya okupansi tutor selalu 0% dan
 * dropdown "tutor available" ikut meleset.
 *
 * Setelah normalisasi, status `tutor_availability` dihitung ulang:
 * slot = "occupied" kalau tutor di-assign ke class session yang punya
 * jadwal di hari/jam itu DAN kelas itu masih punya siswa aktif/waitlist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. schedules ────────────────────────────────────────────────
        foreach (DB::table('schedules')->get() as $row) {
            $day = ScheduleFormat::day($row->day);
            $tb = ScheduleFormat::timeBlock($row->time_block);
            if ($day !== $row->day || $tb !== $row->time_block) {
                DB::table('schedules')->where('id', $row->id)->update([
                    'day' => $day,
                    'time_block' => $tb,
                ]);
            }
        }

        // ── 2. tutor_availability (ada unique tutor_id+day+time_block) ───
        $seen = [];
        foreach (DB::table('tutor_availability')->orderByRaw("status = 'not_available' DESC")->orderBy('id')->get() as $row) {
            $day = ScheduleFormat::day($row->day);
            $tb = ScheduleFormat::timeBlock($row->time_block);
            $key = $row->tutor_id.'|'.$day.'|'.$tb;

            if (isset($seen[$key])) {
                // Duplikat setelah normalisasi — buang, slot sudah ada.
                DB::table('tutor_availability')->where('id', $row->id)->delete();

                continue;
            }
            $seen[$key] = true;

            if ($day !== $row->day || $tb !== $row->time_block) {
                DB::table('tutor_availability')->where('id', $row->id)->update([
                    'day' => $day,
                    'time_block' => $tb,
                ]);
            }
        }

        // ── 3. hitung ulang okupansi ───────────────────────────────────
        DB::table('tutor_availability')->where('status', 'occupied')->update(['status' => 'available']);

        $occupied = DB::table('class_session_tutor as cst')
            ->join('schedules as s', 's.class_session_id', '=', 'cst.class_session_id')
            ->whereIn('cst.class_session_id', function ($q) {
                $q->select('class_session_id')
                    ->from('enrollments')
                    ->whereIn('status', ['active', 'waitlist'])
                    ->whereNotNull('class_session_id');
            })
            ->distinct()
            ->get(['cst.tutor_id', 's.day', 's.time_block']);

        foreach ($occupied as $slot) {
            $affected = DB::table('tutor_availability')
                ->where('tutor_id', $slot->tutor_id)
                ->where('day', $slot->day)
                ->where('time_block', $slot->time_block)
                ->update(['status' => 'occupied']);

            // Tutor mengajar di slot yang belum pernah ia daftarkan → tetap
            // catat sebagai slot occupied (sama seperti
            // TutorAssignmentService::recomputeAvailability()).
            if ($affected === 0) {
                DB::table('tutor_availability')->insert([
                    'tutor_id' => $slot->tutor_id,
                    'day' => $slot->day,
                    'time_block' => $slot->time_block,
                    'status' => 'occupied',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Normalisasi format tidak bisa (dan tidak perlu) dibalik.
    }
};
