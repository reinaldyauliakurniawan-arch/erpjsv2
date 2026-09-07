<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Tutor;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Titik pusat untuk mengirim notifikasi in-app antar peran. Semua trigger
 * (assign tutor, siswa baru, honor pending, payroll, cicilan telat, enrollment
 * mau habis) lewat sini supaya konsisten & gampang diubah.
 */
class Notifier
{
    /** Kirim ke satu user (aman kalau null). */
    public function toUser(?User $user, string $type, string $title, string $body = '', ?string $url = null): void
    {
        if ($user) {
            $user->notify(new AppNotification($type, $title, $body, $url));
        }
    }

    /** Kirim ke semua user dengan role tertentu. */
    public function toRole(string $role, string $type, string $title, string $body = '', ?string $url = null): void
    {
        Notification::send(
            User::where('role', $role)->get(),
            new AppNotification($type, $title, $body, $url),
        );
    }

    /** Kirim ke user dari sebuah Tutor. */
    public function toTutor(Tutor|int $tutor, string $type, string $title, string $body = '', ?string $url = null): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $this->toUser($tutor?->user, $type, $title, $body, $url);
    }

    /** Kirim ke semua tutor sebuah class session. */
    public function toClassTutors(ClassSession $cs, string $type, string $title, string $body = '', ?string $url = null): void
    {
        $users = User::whereIn('id', $cs->tutors()->with('user')->get()->pluck('user_id')->filter())->get();
        Notification::send($users, new AppNotification($type, $title, $body, $url));
    }

    // ── shortcut per-event (dipanggil dari service/controller) ─────────────

    public function tutorAssigned(Tutor|int $tutor, ClassSession $cs): void
    {
        $this->toTutor($tutor, 'tutor_assigned',
            'Kamu di-assign ke kelas',
            "Kelas: {$cs->name}. Menunggu konfirmasi admin.",
            route('tutor.schedule.index'));
    }

    public function tutorConfirmed(Tutor|int $tutor, ClassSession $cs): void
    {
        $this->toTutor($tutor, 'tutor_confirmed',
            'Penugasan kelas dikonfirmasi',
            "Kamu resmi mengajar kelas: {$cs->name}.",
            route('tutor.schedule.index'));
    }

    public function newStudentInClass(Enrollment $enrollment): void
    {
        if (! $enrollment->classSession) {
            return;
        }
        $name = $enrollment->student?->user?->name ?? 'Siswa baru';
        $this->toClassTutors($enrollment->classSession, 'new_student',
            'Siswa baru di kelasmu',
            "{$name} masuk kelas {$enrollment->classSession->name}.",
            route('tutor.schedule.index'));
    }

    public function pendingRate(Tutor|int $tutor, string $date): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $this->toRole('cfo', 'pending_rate',
            'Honor tutor belum ada tarif',
            "Tutor {$tutor?->user?->name} mengajar {$date} tapi belum ada tarif. Set tarif untuk memposting honornya.",
            route('finance.index'));
    }

    public function payrollPaid(Tutor|int $tutor, string $monthLabel, float $amount): void
    {
        $this->toTutor($tutor, 'payroll_paid',
            'Honor sudah dibayar',
            "Honor {$monthLabel}: Rp ".number_format($amount, 0, ',', '.').' sudah dibayar.',
            route('tutor.dashboard'));
    }

    public function overdueInstallments(int $count, float $total): void
    {
        if ($count < 1) {
            return;
        }
        $body = "{$count} cicilan lewat jatuh tempo, total Rp ".number_format($total, 0, ',', '.').'.';
        $this->toRole('admin', 'installment_overdue', 'Cicilan menunggak', $body, route('admin.dashboard'));
        $this->toRole('cfo', 'installment_overdue', 'Cicilan menunggak', $body, route('finance.index'));
    }

    public function enrollmentExpiring(string $studentName, string $programName, string $date, int $daysLeft): void
    {
        $this->toRole('admin', 'enrollment_expiring',
            "Enrollment habis H-{$daysLeft}",
            "{$studentName} ({$programName}) habis {$date}.",
            route('admin.dashboard'));
    }
}
