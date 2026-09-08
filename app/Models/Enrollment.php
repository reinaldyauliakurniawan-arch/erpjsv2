<?php

namespace App\Models;

use App\Services\TutorAssignmentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Enrollment extends Model
{
    use HasFactory;

    /**
     * Tutor yang jam-nya perlu dihitung ulang setelah enrollment ini terhapus.
     * Disnapshot di `deleting` (sebelum pivot dilepas) lalu dipakai di `deleted`.
     */
    public array $tutorIdsPendingRecompute = [];

    protected static function booted()
    {
        // Atomicity fix: previously the cascade deletes (tutors/schedules/installments)
        // ran as 3 separate statements with no transaction. If any failed, the
        // enrollment would NOT be deleted (the parent delete aborts), but the
        // already-detached/deleted children were gone — leaving orphans.
        // Wrap the cascade in a transaction so all-or-nothing semantics hold.
        // Callers of $enrollment->delete() should also wrap in DB::transaction
        // for full atomicity across the cascade + their own writes.
        static::deleting(function (Enrollment $enrollment) {
            // Snapshot tutor (level enrollment + level kelas) untuk recompute jam
            // setelah enrollment hilang.
            $sessionTutorIds = $enrollment->classSession
                ? $enrollment->classSession->tutors()->pluck('tutors.id')
                : collect();
            $enrollment->tutorIdsPendingRecompute = collect($enrollment->tutors()->pluck('tutors.id'))
                ->merge($sessionTutorIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            DB::transaction(function () use ($enrollment) {
                // Buku besar ikut bersih: semua jurnal yang lahir dari enrollment
                // ini (kas pembayaran, pengakuan pendapatan per pertemuan, jurnal
                // per-cicilan, sinkronisasi) dihapus supaya tidak ada
                // pendapatan/kas "hantu" tanpa sumber di trial balance. Jurnal
                // honor tutor (TUTOR-PAY-*, enrollment_id NULL) TIDAK tersentuh —
                // itu milik sesi kelas, bukan enrollment.
                $installmentRefs = $enrollment->installments()->pluck('id')
                    ->map(fn ($iid) => 'INSTALLMENT-'.$iid)->all();
                $journalIds = Journal::where('enrollment_id', $enrollment->id)
                    ->when($installmentRefs, fn ($q) => $q->orWhereIn('reference', $installmentRefs))
                    ->pluck('id');
                if ($journalIds->isNotEmpty()) {
                    DB::table('attendance_tutor')->whereIn('journal_id', $journalIds)->update(['journal_id' => null]);
                    DB::table('journal_items')->whereIn('journal_id', $journalIds)->delete();
                    Journal::whereIn('id', $journalIds)->delete();
                }

                $enrollment->tutors()->detach();

                // Jadwal standalone milik enrollment ini (belum masuk sesi kelas)
                // ikut terhapus. Jadwal yang menempel pada sesi kelas TIDAK
                // dihapus — itu milik kelas & dipakai bersama semua siswanya;
                // kolom enrollment_id-nya di-null-kan otomatis oleh FK.
                Schedule::where('enrollment_id', $enrollment->id)
                    ->whereNull('class_session_id')
                    ->delete();

                $enrollment->installments()->delete();
            });
        });

        static::deleted(function (Enrollment $enrollment) {
            $tutorIds = $enrollment->tutorIdsPendingRecompute;
            if (empty($tutorIds)) {
                return;
            }
            $svc = app(TutorAssignmentService::class);
            foreach ($tutorIds as $tutorId) {
                $svc->recomputeAvailability($tutorId);
            }
        });
    }

    protected $fillable = [
        'student_id', 'program_id', 'class_session_id', 'enrollment_date', 'expiry_date',
        'payment_method', 'payment_channel', 'total_amount', 'payment_status', 'status', 'remaining_meetings',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'enrollment_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    /**
     * Jadwal mingguan enrollment ini = jadwal class session-nya. Jadwal melekat
     * pada kelas (bukan per murid), jadi SEMUA murid di satu kelas grup melihat
     * jadwal yang sama di dashboard-nya. Enrollment tanpa class session (belum
     * ditempatkan) tidak punya jadwal.
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_session_id', 'class_session_id');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function tutors()
    {
        return $this->belongsToMany(Tutor::class, 'enrollment_tutor')->withPivot('status')->withTimestamps();
    }
}
