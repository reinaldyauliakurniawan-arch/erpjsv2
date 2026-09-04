<?php

namespace App\Console\Commands;

use App\Enums\AccountCode;
use App\Enums\PaymentStatus;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MIGRASI FASE 3/4 — TAHAP A (tambahan).
 *
 * Buat enrollment untuk siswa yang MASIH AKTIF tapi pembayarannya sebelum
 * cutoff 1 Juli sehingga tidak ikut terimpor di Fase 1/2. Keputusan owner
 * (2026-09-04): siswa aktif harus ada di ERP; yang murni historis di-skip.
 *
 * Asumsi: lunas di harga list program sebelum era ERP (mereka sudah
 * mengikuti kelas berbulan-bulan). 1 jurnal Dr Kas Bank / Cr Pendapatan
 * Diterima Dimuka. Tiap enrollment diberi catatan agar admin verifikasi.
 *
 *   php artisan migrasi:tahap-a-extra           # dry-run
 *   php artisan migrasi:tahap-a-extra --apply
 */
class MigrasiTahapAExtra extends Command
{
    protected $signature = 'migrasi:tahap-a-extra {--apply}';

    protected $description = 'Fase 3/4 Tahap A — buat enrollment siswa aktif yang di luar cutoff (flagged)';

    private const NOTE = 'MIGRASI 2026-09-04: siswa aktif di luar cutoff 1 Juli. Nominal & tanggal pembayaran ASUMSI (lunas harga list) — perlu verifikasi admin.';

    /** [class_session name, program name, [ [nama, student_id|null, first_meeting] ] ] */
    private const ROWS = [
        ['Novita_Miftah', 'ESP Premium 1 person', [
            ['Miftahurrahman Al Muqri Ghadavi', 123, '2026-06-09'],
        ]],
        ['Yaya_Zidan', 'ESP Premium 1 person', [
            ['Zidan Noor Syahidan', 127, '2026-04-25'],
        ]],
        ['Zizi_ Azzam', 'ESP Premium 1 person', [
            ['Muhammad Azzam Azka', 126, '2026-05-16'],
        ]],
        ['VIP_Kaisan', 'VIP umum', [
            ['Meylino Kaisan', null, '2026-04-04'],
        ]],
        ['DewI&Daka_zalfa dkk', 'ESP Premium 3 person', [
            ['Zalfa Ufaira Eldwin', 43, '2026-04-09'],
            ['Rheista Amira', null, '2026-04-09'],
            ['Trisa Maharani', null, '2026-04-09'],
        ]],
        ['Sandrina_Darra', 'ESP Plus 1 person', [
            ['Darra Davia Habib', null, '2026-08-03'],
        ]],
    ];

    public function handle(AccountingService $accounting): int
    {
        $apply = (bool) $this->option('apply');
        $tag = $apply ? '' : '[DRY-RUN] ';

        DB::beginTransaction();
        try {
            foreach (self::ROWS as [$csName, $progName, $students]) {
                $cs = ClassSession::where('name', $csName)->first();
                $program = Program::where('name', $progName)->firstOrFail();
                if (! $cs) {
                    $this->warn("class_session '{$csName}' tidak ada — dilewati");

                    continue;
                }
                // pastikan program class_session konsisten
                if ($cs->program_id !== $program->id) {
                    $cs->update(['program_id' => $program->id]);
                    $this->line("  {$tag}cs #{$cs->id} program -> {$program->name}");
                }

                foreach ($students as [$name, $studentId, $firstMeeting]) {
                    $student = $studentId ? Student::with('user')->find($studentId) : null;
                    if (! $student) {
                        $student = $this->makeStudent($name);
                    } elseif ($student->user && $student->user->name !== $name) {
                        // koreksi typo nama (mis. "Mumahmmad" -> "Muhammad")
                        $this->line("  {$tag}koreksi nama siswa #{$student->id}: '{$student->user->name}' -> '{$name}'");
                        $student->user->update(['name' => $name]);
                    }

                    $exists = Enrollment::where('student_id', $student->id)
                        ->where('program_id', $program->id)->first();
                    if ($exists) {
                        $this->line("  {$name}: enrollment #{$exists->id} sudah ada — dilewati");

                        continue;
                    }

                    $start = Carbon::parse($firstMeeting);
                    $enr = Enrollment::create([
                        'student_id' => $student->id,
                        'program_id' => $program->id,
                        'class_session_id' => $cs->id,
                        'enrollment_date' => $start->toDateString(),
                        'expiry_date' => $start->copy()->addMonths(12)->toDateString(),
                        'payment_method' => 'full upfront',
                        'payment_channel' => 'bank',
                        'total_amount' => $program->price,
                        'payment_status' => PaymentStatus::FULL->value,
                        'status' => 'active',
                        'remaining_meetings' => $program->total_meetings,
                        'notes' => self::NOTE,
                    ]);

                    $slug = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]/', '')->limit(28, '');
                    $accounting->createJournal(
                        $start->toDateString(),
                        "Migrasi pembayaran (asumsi) - {$name} ({$program->name})",
                        'MIG-EXTRA-'.$slug,
                        [
                            ['account_code' => AccountCode::BANK->value, 'debit' => $program->price, 'credit' => 0],
                            ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $program->price],
                        ],
                        'payment',
                        $program->id,
                        $enr->id,
                    );

                    $this->line(sprintf('  %s+ enr #%d %-28s %s | %s Rp %s | mulai %s | cs #%d',
                        $tag, $enr->id, Str::limit($name, 28), $student->wasRecentlyCreated ? '(student baru)' : "(student #{$student->id})",
                        $program->name, number_format($program->price), $start->toDateString(), $cs->id));
                }
            }

            $tb = DB::table('journal_items')->selectRaw('ROUND(SUM(debit)-SUM(credit),2) d')->value('d');
            $this->newLine();
            $this->line("{$tag}Trial balance diff: {$tb}");

            $apply ? DB::commit() : DB::rollBack();
            $this->info($apply ? 'Tersimpan.' : 'Dry-run — jalankan dengan --apply.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('GAGAL (rollback): '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function makeStudent(string $name): Student
    {
        $slug = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]/', '')->limit(28, '');
        $email = "{$slug}@gmail.com";
        $i = 2;
        while (User::where('email', $email)->exists()) {
            $email = "{$slug}{$i}@gmail.com";
            $i++;
        }
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt(Str::random(24)),
        ]);
        $user->role = 'student';
        $user->save();

        return Student::create(['user_id' => $user->id]);
    }
}
