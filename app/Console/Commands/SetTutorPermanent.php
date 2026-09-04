<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Models\Tutor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Kelola status "tutor tetap" (gaji bulanan pasti) seorang tutor.
 *
 * Sama efeknya dengan halaman Edit Tutor, tapi lewat CLI supaya ada jejak
 * audit yang jelas.
 *
 *   # Angkat jadi tutor tetap (atau re-hire — membuka lagi periode tetap):
 *   php artisan tutor:set-permanent "Sandrina Wahyuning Dias" 3500000 2026-09-01
 *
 *   # Akhiri masa tutor tetap — freelance lagi mulai tanggal <date>:
 *   php artisan tutor:set-permanent "Sandrina Wahyuning Dias" --end=2026-12-01
 *
 *   # Akhiri masa tutor tetap sekaligus non-aktifkan (dipecat / resign):
 *   php artisan tutor:set-permanent "Sandrina Wahyuning Dias" --end=2026-12-01 --fire
 *
 *   # Batalkan total (salah input) — hanya jika belum pernah ada jurnal gaji:
 *   php artisan tutor:set-permanent "Sandrina Wahyuning Dias" --undo
 */
class SetTutorPermanent extends Command
{
    protected $signature = 'tutor:set-permanent
        {name : Nama tutor (persis atau sebagian, harus unik)}
        {salary? : Gaji bulanan (IDR) — hanya untuk mode pengangkatan}
        {since? : Tanggal mulai berlaku (Y-m-d) — hanya untuk mode pengangkatan}
        {--end= : Akhiri masa tutor tetap; tutor jadi freelance lagi mulai tanggal ini (Y-m-d)}
        {--fire : Bersama --end: sekaligus set status tutor jadi inactive}
        {--undo : Batalkan total status tutor tetap (hanya jika belum ada jurnal gaji)}';

    protected $description = 'Angkat / akhiri / batalkan status tutor tetap (gaji bulanan) seorang tutor';

    public function handle(): int
    {
        $tutor = $this->resolveTutor($this->argument('name'));
        if (! $tutor) {
            return self::FAILURE;
        }

        return match (true) {
            (bool) $this->option('undo') => $this->undo($tutor),
            $this->option('end') !== null => $this->end($tutor, $this->option('end'), (bool) $this->option('fire')),
            default => $this->appoint($tutor, $this->argument('salary'), $this->argument('since')),
        };
    }

    private function resolveTutor(string $name): ?Tutor
    {
        $matches = Tutor::whereHas('user', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->with('user')
            ->get();

        if ($matches->isEmpty()) {
            $this->error("Tidak ada tutor yang cocok dengan \"{$name}\".");

            return null;
        }
        if ($matches->count() > 1) {
            $this->error("Ambigu — {$matches->count()} tutor cocok: ".$matches->pluck('user.name')->implode(', '));

            return null;
        }

        return $matches->first();
    }

    private function appoint(Tutor $tutor, ?string $salary, ?string $since): int
    {
        if ($salary === null || $since === null) {
            $this->error('Argumen salary dan since wajib diisi untuk pengangkatan (atau pakai --end / --undo).');

            return self::FAILURE;
        }
        if (! is_numeric($salary) || (float) $salary <= 0) {
            $this->error("Gaji tidak valid: {$salary}");

            return self::FAILURE;
        }
        try {
            $sinceDate = Carbon::parse($since)->startOfDay();
        } catch (\Throwable $e) {
            $this->error("Tanggal tidak valid: {$since}");

            return self::FAILURE;
        }

        $tutor->update([
            'employment_type' => 'permanent',
            'monthly_salary' => (float) $salary,
            'salaried_since' => $sinceDate->toDateString(),
            'salaried_until' => null,
        ]);

        $this->info(sprintf(
            '%s ditetapkan sebagai tutor tetap: Rp %s / bulan, berlaku sejak %s (open-ended).',
            $tutor->user->name,
            number_format((float) $salary),
            $sinceDate->toDateString()
        ));
        $this->line('Meeting sebelum tanggal tsb tetap mengakru fee freelance seperti biasa.');

        return self::SUCCESS;
    }

    private function end(Tutor $tutor, string $freelanceFrom, bool $fire): int
    {
        if (! $tutor->salaried_since) {
            $this->error("{$tutor->user->name} bukan tutor tetap — tidak ada masa yang bisa diakhiri.");

            return self::FAILURE;
        }
        try {
            $freelanceDate = Carbon::parse($freelanceFrom)->startOfDay();
        } catch (\Throwable $e) {
            $this->error("Tanggal tidak valid: {$freelanceFrom}");

            return self::FAILURE;
        }

        $lastSalariedDay = $freelanceDate->copy()->subDay();
        if ($lastSalariedDay->lt($tutor->salaried_since->copy()->startOfDay())) {
            $this->error(sprintf(
                'Tanggal akhir (%s) sebelum tanggal mulai tetap (%s). Kalau ini salah input, pakai --undo.',
                $lastSalariedDay->toDateString(),
                $tutor->salaried_since->toDateString()
            ));

            return self::FAILURE;
        }

        $tutor->update([
            'employment_type' => 'freelance',
            'salaried_until' => $lastSalariedDay->toDateString(),
            'status' => $fire ? 'inactive' : $tutor->status,
        ]);

        $this->info(sprintf(
            '%s: masa tutor tetap berakhir %s. Freelance lagi mulai %s.%s',
            $tutor->user->name,
            $lastSalariedDay->toDateString(),
            $freelanceDate->toDateString(),
            $fire ? ' Status di-set inactive.' : ''
        ));
        $this->line('Gaji & absen untuk periode tetap yang lalu tidak berubah. Payroll bulan terakhir dihitung pro-rata otomatis.');

        return self::SUCCESS;
    }

    private function undo(Tutor $tutor): int
    {
        $hasSalaryJournal = Journal::where('reference', 'like', "PAYROLL-%-TUTOR-{$tutor->id}-SALARY")->exists();
        if ($hasSalaryJournal) {
            $this->error(
                "Tidak bisa --undo: sudah ada jurnal gaji untuk {$tutor->user->name}. ".
                'Pakai --end=<tanggal> untuk mengakhiri masa tetapnya secara benar.'
            );

            return self::FAILURE;
        }

        $tutor->update([
            'employment_type' => 'freelance',
            'monthly_salary' => null,
            'salaried_since' => null,
            'salaried_until' => null,
        ]);

        $this->info("{$tutor->user->name}: status tutor tetap dibatalkan total (dianggap tidak pernah ada).");

        return self::SUCCESS;
    }
}
