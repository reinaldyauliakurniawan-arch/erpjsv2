<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\TutorAvailability;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu untuk menugaskan / melepas tutor.
 *
 * Sebelum ini ada DUA daftar penugasan yang gampang tidak sinkron:
 *   - `class_session_tutor` : dipakai untuk izin isi absensi, lihat jadwal,
 *      aktivasi kelas.
 *   - `enrollment_tutor`    : dipakai untuk widget "Kelas Saya" di dashboard
 *      tutor dan pelepasan slot jam tutor.
 *
 * Service ini SELALU menjaga keduanya + status availability tutor konsisten,
 * apa pun jalur masuknya (halaman Enrollment, halaman Class Session, saat
 * enroll, saat pindah kelas).
 */
class TutorAssignmentService
{
    /**
     * Tugaskan tutor ke sebuah class session DAN ke semua enrollment
     * aktif/waitlist di dalamnya. Idempoten.
     */
    public function assignToClassSession(ClassSession $classSession, int $tutorId, string $status = 'pending'): void
    {
        DB::transaction(function () use ($classSession, $tutorId, $status) {
            if (! $classSession->tutors()->where('tutor_id', $tutorId)->exists()) {
                $classSession->tutors()->attach($tutorId, ['status' => $status]);
            }

            $enrollmentIds = Enrollment::where('class_session_id', $classSession->id)
                ->whereIn('status', ['active', 'waitlist'])
                ->pluck('id');
            foreach ($enrollmentIds as $eid) {
                $this->upsertEnrollmentPivot($eid, $tutorId, $status);
            }

            $this->recomputeAvailability($tutorId);
            $this->maybeActivateWaitlist($classSession);
        });
    }

    /**
     * Lepas tutor dari class session DAN semua enrollment-nya. Bebaskan slot jam.
     */
    public function removeFromClassSession(ClassSession $classSession, int $tutorId): void
    {
        DB::transaction(function () use ($classSession, $tutorId) {
            $classSession->tutors()->detach($tutorId);
            DB::table('enrollment_tutor')
                ->whereIn('enrollment_id', Enrollment::where('class_session_id', $classSession->id)->pluck('id'))
                ->where('tutor_id', $tutorId)
                ->delete();
            $this->recomputeAvailability($tutorId);
        });
    }

    /**
     * Ubah status (pending/confirmed) tutor di KEDUA pivot sekaligus.
     */
    public function setStatus(ClassSession $classSession, int $tutorId, string $status): void
    {
        DB::transaction(function () use ($classSession, $tutorId, $status) {
            $classSession->tutors()->updateExistingPivot($tutorId, ['status' => $status]);
            DB::table('enrollment_tutor')
                ->whereIn('enrollment_id', Enrollment::where('class_session_id', $classSession->id)->pluck('id'))
                ->where('tutor_id', $tutorId)
                ->update(['status' => $status, 'updated_at' => now()]);

            if ($status === 'confirmed') {
                $this->maybeActivateWaitlist($classSession);
            }
        });
    }

    /**
     * Assign lewat level enrollment. Kalau enrollment sudah punya class session,
     * ini otomatis jadi assign ke seluruh kelas (best practice: tutor mengajar
     * kelasnya, bukan satu murid). Kalau belum ada class session, cukup pivot
     * enrollment.
     */
    public function assignToEnrollment(Enrollment $enrollment, int $tutorId, string $status = 'pending'): void
    {
        if ($enrollment->class_session_id) {
            $this->assignToClassSession($enrollment->classSession, $tutorId, $status);

            return;
        }
        DB::transaction(fn () => $this->upsertEnrollmentPivot($enrollment->id, $tutorId, $status));
    }

    /**
     * Lepas lewat level enrollment. Kalau tidak ada enrollment lain di kelas
     * yang masih pakai tutor ini, lepas juga dari class session.
     */
    public function removeFromEnrollment(Enrollment $enrollment, int $tutorId): void
    {
        DB::transaction(function () use ($enrollment, $tutorId) {
            DB::table('enrollment_tutor')
                ->where('enrollment_id', $enrollment->id)
                ->where('tutor_id', $tutorId)
                ->delete();

            if (! $enrollment->class_session_id) {
                return;
            }

            $siblingStillUses = Enrollment::where('class_session_id', $enrollment->class_session_id)
                ->where('id', '!=', $enrollment->id)
                ->whereHas('tutors', fn ($q) => $q->where('tutor_id', $tutorId))
                ->exists();
            if (! $siblingStillUses && $enrollment->classSession) {
                $enrollment->classSession->tutors()->detach($tutorId);
            }
            $this->recomputeAvailability($tutorId);
        });
    }

    /**
     * Saat sebuah enrollment MASUK ke class session (baru enroll / dipindah),
     * salin semua tutor kelas itu ke pivot enrollment-nya.
     */
    public function syncEnrollmentToClassTutors(Enrollment $enrollment): void
    {
        if (! $enrollment->class_session_id || ! $enrollment->classSession) {
            return;
        }
        DB::transaction(function () use ($enrollment) {
            foreach ($enrollment->classSession->tutors()->get() as $tutor) {
                $this->upsertEnrollmentPivot($enrollment->id, $tutor->id, $tutor->pivot->status ?? 'pending');
                $this->recomputeAvailability($tutor->id);
            }
        });
    }

    // ── internal ──────────────────────────────────────────────────────────

    private function upsertEnrollmentPivot(int $enrollmentId, int $tutorId, string $status): void
    {
        $exists = DB::table('enrollment_tutor')
            ->where('enrollment_id', $enrollmentId)->where('tutor_id', $tutorId)->exists();
        if ($exists) {
            DB::table('enrollment_tutor')
                ->where('enrollment_id', $enrollmentId)->where('tutor_id', $tutorId)
                ->update(['status' => $status, 'updated_at' => now()]);
        } else {
            DB::table('enrollment_tutor')->insert([
                'enrollment_id' => $enrollmentId, 'tutor_id' => $tutorId,
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Hitung ulang SEMUA slot availability tutor dari nol: sebuah slot =
     * "occupied" kalau tutor ini di-assign ke class session yang (a) punya
     * jadwal di hari/jam itu dan (b) masih punya siswa aktif/waitlist.
     * Slot "occupied" yang tak lagi terpakai dikembalikan ke "available".
     * Slot "not_available" (pilihan tutor) dibiarkan, kecuali sekarang benar
     * dipakai kelas → jadi "occupied".
     */
    public function recomputeAvailability(int $tutorId): void
    {
        $shouldBeOccupied = Schedule::query()
            ->whereNotNull('class_session_id')
            ->whereHas('classSession.tutors', fn ($q) => $q->where('tutor_id', $tutorId))
            ->whereHas('classSession.enrollments', fn ($q) => $q->whereIn('status', ['active', 'waitlist']))
            ->get(['day', 'time_block'])
            ->map(fn ($s) => $s->day.'|'.$s->time_block)
            ->unique();

        foreach (TutorAvailability::where('tutor_id', $tutorId)->get() as $slot) {
            $key = $slot->day.'|'.$slot->time_block;
            if ($shouldBeOccupied->contains($key)) {
                if ($slot->status !== 'occupied') {
                    $slot->update(['status' => 'occupied']);
                }
            } elseif ($slot->status === 'occupied') {
                $slot->update(['status' => 'available']);
            }
        }

        foreach ($shouldBeOccupied as $key) {
            [$day, $timeBlock] = explode('|', $key);
            $slot = TutorAvailability::firstOrCreate(
                ['tutor_id' => $tutorId, 'day' => $day, 'time_block' => $timeBlock],
                ['status' => 'occupied'],
            );
            if ($slot->status !== 'occupied') {
                $slot->update(['status' => 'occupied']);
            }
        }
    }

    private function maybeActivateWaitlist(ClassSession $classSession): void
    {
        $classSession->loadMissing('program');
        if (! $classSession->program) {
            return;
        }
        $hasConfirmedTutor = $classSession->tutors()->wherePivot('status', 'confirmed')->exists();
        $activeCount = Enrollment::where('class_session_id', $classSession->id)
            ->whereIn('status', ['active', 'waitlist'])
            ->lockForUpdate()
            ->count();

        if ($hasConfirmedTutor && $activeCount >= (int) $classSession->program->min_quota) {
            Enrollment::where('class_session_id', $classSession->id)
                ->where('status', 'waitlist')
                ->update(['status' => 'active']);
            if ($classSession->status !== 'active') {
                $classSession->update(['status' => 'active']);
            }
        }
    }
}
