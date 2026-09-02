<?php

namespace App\Console\Commands;

use App\Enums\AccountCode;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\EnrollmentLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Koreksi cohort "EDC Reguler 5 Bulan" (level PI/IN).
 *
 * Migrasi lama masukin semua peserta EDC ke program "EDC Regular" (1,2jt / 24 mtg).
 * Padahal peserta level Pre-Intermediate & Intermediate ikut paket 5 bulan
 * (Rp 2.000.000 / 40 meeting). Command ini:
 *   A. 12 enrollment yang SUDAH ada di DB -> pindahkan program + betulkan total_amount,
 *      remaining_meetings, cicilan, payment_status, lalu sync buku besar.
 *   B. 7 peserta yang BELUM ada di DB -> buat student + enrollment + jurnal pembayaran.
 *
 *   php artisan edc:fix-5-bulan            # dry-run
 *   php artisan edc:fix-5-bulan --apply
 */
class FixEdc5BulanCohort extends Command
{
    protected $signature = 'edc:fix-5-bulan {--apply : Tulis perubahan (default dry-run)}';

    protected $description = 'Koreksi program peserta EDC level PI/IN ke paket 5 bulan (2jt / 40 mtg)';

    private const PROGRAM = [
        'name' => 'EDC Reguler 5 Bulan',
        'price' => 2_000_000,
        'total_meetings' => 40,
        'type' => 'group',
        'min_quota' => 6,
    ];

    /** enrollment_id => attended (dari file Absen; remaining_meetings = 40 - attended) */
    private const CAT_A = [
        81 => 9, 82 => 11, 83 => 12, 84 => 6, 85 => 11, 86 => 15,
        87 => 6, 88 => 11, 97 => 9, 98 => 7, 99 => 8, 100 => 8,
    ];

    /** peserta baru: [nama, phone, education_level, attended, reuse_email, pembayaran[[jumlah, tgl]]] */
    private const CAT_B = [
        ['name' => 'Regina Nabila Parsa', 'attended' => 10, 'pays' => [[2_000_000, '2026-07-06']]],
        ['name' => 'Heyka Lukmanul Hakim', 'attended' => 4, 'pays' => [[1_000_000, '2026-05-13']]],
        ['name' => 'Adrian Kenzie Winata', 'reuse_email' => 'adrian@gmail.com', 'attended' => 8, 'pays' => [[2_000_000, '2026-07-15']]],
        ['name' => 'Tasya Valencia Putri', 'phone' => '89530373100', 'education_level' => 'SMA', 'attended' => 9, 'pays' => [[2_000_000, '2026-08-06']]],
        ['name' => 'Leonell Richie Theja Angga Kusuma', 'attended' => 8, 'pays' => [[2_000_000, '2026-07-11']]],
        ['name' => 'Ibrahim Praba Mahendra', 'attended' => 9, 'pays' => [[1_000_000, '2026-07-17']]],
        ['name' => 'Muhammad Ghanie Genius', 'attended' => 8, 'pays' => [[800_000, '2026-07-14']]],
    ];

    public function handle(EnrollmentLedgerService $ledger, AccountingService $accounting): int
    {
        $apply = (bool) $this->option('apply');
        $tag = $apply ? '' : '[DRY-RUN] ';

        DB::beginTransaction();
        try {
            $program = $this->ensureProgram();
            $this->line("{$tag}Program: {$program->name} (#{$program->id}) — Rp ".number_format($program->price)." / {$program->total_meetings} mtg");

            $this->newLine();
            $this->info('== A. Repoint 12 enrollment yang sudah ada ==');
            foreach (self::CAT_A as $enrId => $attended) {
                $this->repointExisting($enrId, $attended, $program, $ledger);
            }

            $this->newLine();
            $this->info('== B. Buat 7 enrollment baru ==');
            foreach (self::CAT_B as $row) {
                $this->createNew($row, $program, $accounting, $ledger);
            }

            $tb = DB::table('journal_items')->selectRaw('ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c')->first();
            $this->newLine();
            $this->line("{$tag}Trial balance: {$tb->d} vs {$tb->c} (selisih ".round((float) $tb->d - (float) $tb->c, 2).')');

            $apply ? DB::commit() : DB::rollBack();
            $this->info($apply ? 'Tersimpan.' : 'Dry-run — tidak ada yang ditulis. Jalankan dengan --apply.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('GAGAL (rollback): '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ensureProgram(): Program
    {
        return Program::firstOrCreate(
            ['name' => self::PROGRAM['name']],
            [
                'price' => self::PROGRAM['price'],
                'total_meetings' => self::PROGRAM['total_meetings'],
                'type' => self::PROGRAM['type'],
                'min_quota' => self::PROGRAM['min_quota'],
            ]
        );
    }

    private function repointExisting(int $enrId, int $attended, Program $program, EnrollmentLedgerService $ledger): void
    {
        $e = Enrollment::with(['program', 'installments', 'student.user'])->lockForUpdate()->find($enrId);
        if (! $e) {
            $this->warn("  #{$enrId} tidak ditemukan — dilewati");

            return;
        }

        $price = (string) self::PROGRAM['price'];
        $isInstallment = $e->payment_method === 'installment';
        // full upfront = kas masuk = total kontrak (semantik RevenueRecognitionService)
        $paidSum = $isInstallment
            ? (string) $e->installments()->whereNotNull('paid_at')->sum('amount')
            : $price;
        $newRem = max(0, $program->total_meetings - $attended);

        $e->update([
            'program_id' => $program->id,
            'total_amount' => self::PROGRAM['price'],
            'remaining_meetings' => $newRem,
        ]);

        // Cicilan: hanya untuk enrollment installment. Baris belum-lunas
        // disesuaikan supaya total semua baris == 2jt.
        if ($isInstallment) {
            $e->installments()->whereNull('paid_at')->delete();
            $gap = bcsub($price, $paidSum, 2);
            if (bccomp($gap, '0.01', 2) > 0) {
                Installment::create([
                    'enrollment_id' => $e->id,
                    'amount' => $gap,
                    'due_date' => optional($e->expiry_date)->toDateString() ?? now()->toDateString(),
                    'paid_at' => null,
                    'payment_channel' => $e->payment_channel,
                ]);
            }
        }

        $e->payment_status = bccomp($paidSum, bcsub($price, '0.01', 2), 2) >= 0
            ? PaymentStatus::FULL->value
            : (bccomp($paidSum, '0.01', 2) < 0 ? PaymentStatus::PENDING->value : PaymentStatus::PARTIAL->value);
        $e->save();

        $sync = $ledger->reconcile($e->refresh()->load('program'), null, 'Koreksi program ke EDC Reguler 5 Bulan');
        $ok = $ledger->isInSync($e->refresh()->load('program'));

        $this->line(sprintf(
            '  #%d %-30s paid=%s/2.000.000  attended=%d rem=%d  status=%s  %s%s',
            $e->id, Str::limit($e->student->user->name, 30), number_format((float) $paidSum),
            $attended, $newRem, $e->payment_status,
            $sync ? "jurnal {$sync->reference} " : '',
            $ok ? 'sinkron OK' : 'BELUM SINKRON'
        ));
    }

    private function createNew(array $row, Program $program, AccountingService $accounting, EnrollmentLedgerService $ledger): void
    {
        $name = $row['name'];
        $pays = $row['pays'];
        $paidSum = array_sum(array_column($pays, 0));
        $firstDate = collect($pays)->min(fn ($p) => $p[1]);
        $slug = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]/', '')->limit(30, '');

        // student: reuse (Adrian) atau buat baru
        if (! empty($row['reuse_email'])) {
            $user = User::where('email', $row['reuse_email'])->firstOrFail();
            $student = $user->student ?? Student::where('user_id', $user->id)->firstOrFail();
            $note = "(reuse {$user->email})";
        } else {
            $email = "{$slug}@gmail.com";
            $i = 2;
            while (User::where('email', $email)->exists()) {
                $email = "{$slug}{$i}@gmail.com";
                $i++;
            }
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $row['phone'] ?? null,
                'password' => bcrypt(Str::random(24)),
            ]);
            $user->role = 'student';
            $user->save();
            $student = Student::create([
                'user_id' => $user->id,
                'education_level' => $row['education_level'] ?? null,
            ]);
            $note = "(user baru {$email})";
        }

        // enrollment sudah ada? (idempoten)
        $existing = Enrollment::where('student_id', $student->id)->where('program_id', $program->id)->first();
        if ($existing) {
            $this->line("  {$name}: enrollment #{$existing->id} sudah ada — dilewati");

            return;
        }

        $method = $paidSum >= self::PROGRAM['price'] ? 'full upfront' : 'installment';
        $enrollmentDate = Carbon::parse($firstDate);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'class_session_id' => null,
            'enrollment_date' => $enrollmentDate->toDateString(),
            'expiry_date' => $enrollmentDate->copy()->addMonths(6)->toDateString(),
            'payment_method' => $method,
            'payment_channel' => 'bank',
            'total_amount' => self::PROGRAM['price'],
            'payment_status' => $paidSum >= self::PROGRAM['price'] ? PaymentStatus::FULL->value : PaymentStatus::PARTIAL->value,
            'status' => 'active',
            'remaining_meetings' => max(0, $program->total_meetings - $row['attended']),
        ]);

        if ($method === 'installment') {
            foreach ($pays as $k => [$amt, $date]) {
                Installment::create([
                    'enrollment_id' => $enrollment->id,
                    'amount' => $amt,
                    'due_date' => $date,
                    'paid_at' => $date,
                    'payment_channel' => 'bank',
                ]);
            }
            $gap = self::PROGRAM['price'] - $paidSum;
            if ($gap > 0) {
                Installment::create([
                    'enrollment_id' => $enrollment->id,
                    'amount' => $gap,
                    'due_date' => $enrollment->expiry_date->toDateString(),
                    'paid_at' => null,
                    'payment_channel' => 'bank',
                ]);
            }
        }

        // jurnal pembayaran: Dr Kas di Bank / Cr Pendapatan Diterima Dimuka
        foreach ($pays as $k => [$amt, $date]) {
            $accounting->createJournal(
                $date,
                "Migrasi pembayaran - {$name} ({$program->name})",
                'MIG-EDC5-'.$slug.'-'.Carbon::parse($date)->format('Ymd').'-'.($k + 1),
                [
                    ['account_code' => AccountCode::BANK->value, 'debit' => $amt, 'credit' => 0],
                    ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $amt],
                ],
                'payment',
                $program->id,
                $enrollment->id,
            );
        }

        $ok = $ledger->isInSync($enrollment->refresh()->load('program'));
        $this->line(sprintf(
            '  %-32s enr #%d  %s  paid=%s/2.000.000  %s  rem=%d  %s  %s',
            Str::limit($name, 32), $enrollment->id, $note, number_format($paidSum), $method,
            $enrollment->remaining_meetings, $enrollment->payment_status, $ok ? '✓ sinkron' : '✗ BELUM SINKRON'
        ));
    }
}
