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

    /**
     * Ada honor tutor yang baru tercatat untuk bulan yang payroll-nya SUDAH
     * dijalankan — kalau tidak dibayar susulan, honor ini terlewat. Kabari CFO.
     */
    public function feeAfterPayrollApproved(Tutor|int $tutor, string $monthLabel): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $this->toRole('cfo', 'fee_after_payroll',
            'Honor tutor terlewat dari payroll',
            "Honor {$tutor?->user?->name} untuk {$monthLabel} baru tercatat padahal payroll bulan itu sudah dijalankan. "
            .'Buat payroll run bulan itu sekali lagi untuk membayar susulan.',
            route('finance.payroll.index'));
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

    // ── Booking ruangan: aksi tutor -> admin, aksi admin -> tutor ──────────

    /** Tutor mem-booking ruang sementara → beri tahu admin. */
    public function roomBookedByTutor(Tutor|int $tutor, string $room, string $date, string $block, ?string $notes = null): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $name = $tutor?->user?->name ?? 'Tutor';
        $extra = $notes ? " — {$notes}" : '';
        $this->toRole('admin', 'room_booked',
            'Tutor booking ruangan',
            "{$name} booking {$room} pada {$date} {$block}{$extra}.",
            route('admin.schedule.index'));
    }

    /** Tutor menandai satu pertemuan reguler sebagai skip → beri tahu admin. */
    public function sessionSkippedByTutor(Tutor|int $tutor, ?string $sessionName, string $room, string $date, string $block, ?string $reason = null): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $name = $tutor?->user?->name ?? 'Tutor';
        $extra = $reason ? " Alasan: {$reason}." : '';
        $this->toRole('admin', 'session_skipped',
            'Tutor skip pertemuan',
            "{$name} skip ".($sessionName ? "kelas {$sessionName}" : 'sesi')." di {$room} pada {$date} {$block}.{$extra}",
            route('admin.schedule.index'));
    }

    /** Tutor membatalkan booking/skip miliknya → beri tahu admin. */
    public function roomBookingCancelledByTutor(Tutor|int $tutor, string $kindLabel, string $room, string $date, string $block): void
    {
        $tutor = $tutor instanceof Tutor ? $tutor : Tutor::with('user')->find($tutor);
        $name = $tutor?->user?->name ?? 'Tutor';
        $this->toRole('admin', 'room_booking_cancelled',
            'Tutor batalkan '.$kindLabel,
            "{$name} membatalkan {$kindLabel} {$room} pada {$date} {$block}.",
            route('admin.schedule.index'));
    }

    /** Admin men-skip pertemuan reguler → beri tahu tutor-tutor kelas itu. */
    public function sessionSkippedByAdmin(ClassSession $cs, string $date, string $block): void
    {
        $this->toClassTutors($cs, 'session_skipped',
            'Pertemuan di-skip admin',
            "Pertemuan kelas {$cs->name} pada {$date} {$block} ditiadakan oleh admin.",
            route('tutor.schedule.index'));
    }

    /** Admin membatalkan booking/skip milik tutor → beri tahu tutor itu. */
    public function roomBookingRemovedByAdmin(Tutor|int $tutor, string $kindLabel, string $room, string $date, string $block): void
    {
        $this->toTutor($tutor, 'room_booking_removed',
            ucfirst($kindLabel).' dibatalkan admin',
            "Admin membatalkan {$kindLabel} {$room} pada {$date} {$block}.",
            route('tutor.schedule.index'));
    }

    /** Admin mem-booking ruang untuk seorang tutor → beri tahu tutor itu. */
    public function roomBookedForTutorByAdmin(Tutor|int $tutor, string $room, string $date, string $block): void
    {
        $this->toTutor($tutor, 'room_booked',
            'Ruangan dibooking untukmu',
            "Admin membooking {$room} untukmu pada {$date} {$block}.",
            route('tutor.schedule.index'));
    }
}
