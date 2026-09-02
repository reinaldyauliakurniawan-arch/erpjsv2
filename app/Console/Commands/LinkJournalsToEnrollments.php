<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\Program;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Isi kolom journals.enrollment_id untuk jurnal-jurnal lama yang belum
 * terhubung. Idempoten: hanya menyentuh baris yang enrollment_id-nya masih
 * NULL. Aman dijalankan berkali-kali.
 *
 *   php artisan journals:link-enrollments
 *   php artisan journals:link-enrollments --dry-run
 */
class LinkJournalsToEnrollments extends Command
{
    protected $signature = 'journals:link-enrollments {--dry-run : Tampilkan rencana tanpa menulis}';

    protected $description = 'Hubungkan jurnal lama (PAYMENT-ENROLL, INSTALLMENT, REV-REC, MIG-*) ke enrollment sumbernya';

    /** @var Collection<int,array{id:int,norm:string}> */
    private $userNames;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $matched = 0;
        $ambiguous = [];
        $unmatched = [];

        $journals = Journal::whereNull('enrollment_id')->get();
        $this->info("Jurnal tanpa enrollment_id: {$journals->count()}");

        // Cache lookup untuk MIG
        $this->userNames = User::where('role', 'student')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'norm' => $this->norm($u->name)]);
        $programByName = Program::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id]);

        foreach ($journals as $j) {
            $enrollmentId = $this->resolve($j, $programByName, $ambiguous, $unmatched);
            if (! $enrollmentId) {
                continue;
            }
            $matched++;
            if (! $dry) {
                DB::table('journals')->where('id', $j->id)->update(['enrollment_id' => $enrollmentId]);
            }
        }

        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '')."Terhubung: {$matched}");
        if ($ambiguous) {
            $this->warn('Ambigu (>1 enrollment cocok, dilewati):');
            foreach ($ambiguous as $line) {
                $this->line("  {$line}");
            }
        }
        if ($unmatched) {
            $this->warn('Tidak ketemu (dilewati):');
            foreach ($unmatched as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function norm(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Cari user by nama: exact-norm dulu, lalu subset token / Jaccard >= 0.6. */
    private function resolveUserIds(string $name): array
    {
        $target = $this->norm($name);
        if ($target === '') {
            return [];
        }
        $exact = $this->userNames->where('norm', $target)->pluck('id')->all();
        if ($exact) {
            return $exact;
        }

        $tt = array_filter(explode(' ', $target));
        $best = [];
        $bestScore = 0.0;
        foreach ($this->userNames as $u) {
            $ut = array_filter(explode(' ', $u['norm']));
            if (! $ut) {
                continue;
            }
            $inter = count(array_intersect($tt, $ut));
            if ($inter === 0) {
                continue;
            }
            $subset = ($inter === count($tt) || $inter === count($ut)) && $inter >= 2;
            $jac = $inter / count(array_unique(array_merge($tt, $ut)));
            if ($subset || $jac >= 0.6) {
                if ($jac > $bestScore) {
                    $bestScore = $jac;
                    $best = [$u['id']];
                } elseif ($jac === $bestScore) {
                    $best[] = $u['id'];
                }
            }
        }

        return $best;
    }

    private function resolve(Journal $j, $programByName, array &$ambiguous, array &$unmatched): ?int
    {
        $ref = $j->reference;

        // Buang prefix reversal berulang: REV-REV-... -> ...
        while (str_starts_with($ref, 'REV-')) {
            $ref = substr($ref, 4);
        }

        // id enrollment langsung di reference
        if (preg_match('/^(?:PAYMENT-ENROLL|MANUAL-EXPIRY|ENR-SYNC)-(\d+)/', $ref, $m)) {
            return Enrollment::whereKey((int) $m[1])->value('id');
        }

        // REV-REC-{attendanceId}-{enrollmentId}
        if (preg_match('/^REV-REC-\d+-(\d+)$/', $ref, $m)) {
            return Enrollment::whereKey((int) $m[1])->value('id');
        }

        // INSTALLMENT-{installmentId}
        if (preg_match('/^INSTALLMENT-(\d+)/', $ref, $m)) {
            return Installment::whereKey((int) $m[1])->value('enrollment_id');
        }

        // Jurnal migrasi: "Migrasi pembayaran - {nama} ({program})"
        if (str_starts_with($ref, 'MIG-')
            && preg_match('/^Migrasi pembayaran - (.+) \(([^()]+)\)\s*$/u', $j->description, $m)) {
            return $this->resolveMig($j, trim($m[1]), trim($m[2]), $programByName, $ambiguous, $unmatched);
        }

        $unmatched[] = "#{$j->id} {$ref}";

        return null;
    }

    private function resolveMig(Journal $j, string $name, string $prog, $programByName, array &$ambiguous, array &$unmatched): ?int
    {
        $userIds = $this->resolveUserIds($name);
        $programId = $programByName[mb_strtolower(trim($prog))] ?? null;

        if (empty($userIds) || ! $programId) {
            $unmatched[] = "#{$j->id} MIG '{$name}' / '{$prog}' (user/program tak ketemu)";

            return null;
        }

        $candidates = Enrollment::whereHas('student', fn ($q) => $q->whereIn('user_id', $userIds))
            ->where('program_id', $programId)
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first()->id;
        }
        if ($candidates->isEmpty()) {
            $unmatched[] = "#{$j->id} MIG '{$name}' / '{$prog}' (enrollment tak ketemu)";

            return null;
        }

        // >1 enrollment (renewal): cocokkan lewat tanggal + nominal pembayaran.
        $date = (string) $j->date;
        $amount = (string) $j->total_amount;

        $byInstallment = $candidates->filter(fn ($e) => Installment::where('enrollment_id', $e->id)
            ->whereDate('paid_at', $date)
            ->whereRaw('ABS(amount - ?) < 1', [$amount])
            ->exists());
        if ($byInstallment->count() === 1) {
            return $byInstallment->first()->id;
        }

        $byEnrollDate = $candidates->filter(fn ($e) => optional($e->enrollment_date)->toDateString() === $date);
        if ($byEnrollDate->count() === 1) {
            return $byEnrollDate->first()->id;
        }

        $ambiguous[] = "#{$j->id} MIG '{$name}' / '{$prog}' -> enrollments [".$candidates->pluck('id')->join(', ').']';

        return null;
    }
}
