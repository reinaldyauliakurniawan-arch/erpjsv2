<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\RoomBooking;
use App\Services\EnrollmentLedgerService;
use App\Services\Notifier;
use App\Services\TutorAssignmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckExpirations extends Command
{
    protected $signature = 'app:check-expirations';

    protected $description = 'Check for expiring enrollments and handle automatic revenue recognition for expired ones.';

    public function __construct(
        protected EnrollmentLedgerService $ledgerService,
        protected TutorAssignmentService $tutorAssignment,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $today = Carbon::today();

        // 1. Warnings for H-7 and H-3
        $h7 = Enrollment::with(['student.user', 'program'])
            ->where('expiry_date', $today->copy()->addDays(7))
            ->where('status', 'active')
            ->get();

        $h3 = Enrollment::with(['student.user', 'program'])
            ->where('expiry_date', $today->copy()->addDays(3))
            ->where('status', 'active')
            ->get();

        $notifier = app(Notifier::class);
        foreach ($h7 as $e) {
            $this->info("H-7 Expiry Warning: Student {$e->student->user->name} ({$e->program->name}) expires on {$e->expiry_date}");
            $notifier->enrollmentExpiring($e->student->user->name, $e->program->name, (string) $e->expiry_date, 7);
        }
        foreach ($h3 as $e) {
            $this->warn("H-3 Expiry Warning: Student {$e->student->user->name} ({$e->program->name}) expires on {$e->expiry_date}");
            $notifier->enrollmentExpiring($e->student->user->name, $e->program->name, (string) $e->expiry_date, 3);
        }

        // 2. Enrollment yang sudah lewat tanggal habis → hanguskan.
        //    Perlakuannya PERSIS sama dengan tombol "Expire" manual di halaman
        //    Enrollment: buku besar dibangun ulang dari data operasional lewat
        //    EnrollmentLedgerService::rebuild() — sisa "pendapatan diterima di
        //    muka" yang benar-benar tersedia diakui jadi pendapatan (bukan
        //    rumus lama remaining_meetings × harga-per-pertemuan yang bisa
        //    menyeret saldo jadi minus untuk siswa cicilan yang nunggak).
        //    Piutang siswa yang nunggak TIDAK ikut dihapus — itu tetap tagihan.
        $expired = Enrollment::with(['student.user', 'program', 'tutors'])
            ->where('expiry_date', '<', $today)
            ->where('status', 'active')
            ->get();

        foreach ($expired as $expiredEnrollment) {
            try {
                DB::transaction(function () use ($expiredEnrollment, $today) {
                    $e = Enrollment::with('program')->lockForUpdate()->find($expiredEnrollment->id);
                    if (! $e || $e->status !== 'active') {
                        return;
                    }

                    $tutorIds = $e->tutors()->pluck('tutors.id')->map(fn ($v) => (int) $v)->all();

                    $e->update([
                        'status' => 'expired',
                        'remaining_meetings' => 0,
                    ]);

                    $e->refresh()->load('program');
                    // Sama seperti jalur edit enrollment: buku besar dibangun
                    // ulang dengan tanggal dasar = tanggal enrollment.
                    $this->ledgerService->rebuild($e, optional($e->enrollment_date)->toDateString());

                    // Bersihkan booking ruang masa depan + bebaskan jam tutor.
                    RoomBooking::where('enrollment_id', $e->id)
                        ->where('date', '>', $today->toDateString())
                        ->delete();

                    foreach ($tutorIds as $tutorId) {
                        $this->tutorAssignment->recomputeAvailability($tutorId);
                    }

                    $this->info("Enrollment #{$e->id} ({$expiredEnrollment->student->user->name}) dihanguskan; buku besar disinkronkan.");
                });
            } catch (\Throwable $ex) {
                $this->error("Gagal menghanguskan enrollment #{$expiredEnrollment->id}: ".$ex->getMessage());
            }
        }
    }
}
