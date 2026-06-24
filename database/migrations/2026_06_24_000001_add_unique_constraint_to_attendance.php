<?php

/**
 * Add unique constraint on attendance (class_session_id, date, time_block).
 *
 * Race-condition fix: AttendanceService::markAttendance uses
 * ->lockForUpdate()->first() to check for an existing attendance row, but
 * SELECT ... FOR UPDATE locks nothing when no row matches. Two concurrent
 * requests for the same (class_session_id, date, time_block) both see
 * $isNew = true and both insert — creating duplicate attendance rows with
 * duplicate revenue-recognition journals.
 *
 * The DB-level unique constraint is the only reliable defense. The service
 * code should catch UniqueConstraintViolationException and treat it as
 * "already exists" — re-load the existing record and fall through to the
 * "already marked" branch.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any existing duplicates before adding the unique index,
        // otherwise the ALTER will fail. Keep the oldest row per group,
        // delete the rest. This is safe because duplicate attendance rows
        // would have created duplicate revenue-recognition journals — the
        // older journal is the "canonical" one and the newer should have
        // been prevented.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                DELETE a1 FROM attendance a1
                INNER JOIN attendance a2
                ON a1.class_session_id = a2.class_session_id
                   AND a1.date = a2.date
                   AND a1.time_block = a2.time_block
                   AND a1.id > a2.id
            ");
        } else {
            // SQLite doesn't support DELETE with JOIN. Use a subquery.
            $duplicates = DB::table('attendance')
                ->selectRaw('MIN(id) as keep_id, class_session_id, date, time_block')
                ->whereNotNull('class_session_id')
                ->groupBy('class_session_id', 'date', 'time_block')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                DB::table('attendance')
                    ->where('class_session_id', $dup->class_session_id)
                    ->where('date', $dup->date)
                    ->where('time_block', $dup->time_block)
                    ->where('id', '!=', $dup->keep_id)
                    ->delete();
            }
        }

        Schema::table('attendance', function (Blueprint $table) {
            // Only enforce uniqueness when class_session_id is set.
            // Some legacy attendance rows may have NULL class_session_id
            // (pre-migration data) — those are exempt.
            // SQLite doesn't support partial indexes via Blueprint, but
            // a regular unique index on (class_session_id, date, time_block)
            // allows multiple NULLs (NULL != NULL in SQL), so it's safe.
            $table->unique(
                ['class_session_id', 'date', 'time_block'],
                'attendance_session_date_block_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique('attendance_session_date_block_unique');
        });
    }
};
