<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\EnrollmentLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bereskan sinkronisasi antara data enrollment (payment_method, cicilan,
 * payment_status) dan buku besar untuk SEMUA enrollment.
 *
 * Langkah per enrollment:
 *   1. reconcileOperational — selaraskan cicilan/metode dg kas yang sudah
 *      diposting (mis. data migrasi yang salah tandai "full upfront" padahal
 *      baru dibayar sebagian).
 *   2. reconcile — posting jurnal penyesuaian selisih kalau masih ada gap.
 *
 *   php artisan enrollments:sync-ledger            # dry-run (default)
 *   php artisan enrollments:sync-ledger --apply    # tulis perubahan
 */
class SyncEnrollmentLedger extends Command
{
    protected $signature = 'enrollments:sync-ledger {--apply : Tulis perubahan (default dry-run)}';

    protected $description = 'Selaraskan data cicilan/metode & buku besar untuk semua enrollment';

    public function handle(EnrollmentLedgerService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $opCount = 0;
        $journalCount = 0;
        $stillBad = [];
        $failed = [];

        $ids = Enrollment::orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::beginTransaction();
            try {
                $e = Enrollment::with(['program', 'installments', 'student.user'])->lockForUpdate()->find($id);

                foreach ($ledger->reconcileOperational($e) as $c) {
                    $opCount++;
                    $this->line("  #{$e->id} {$e->student->user->name}: {$c}");
                }

                $journal = $ledger->reconcile($e->refresh()->load('program'), null, 'Sinkronisasi awal buku besar');
                if ($journal) {
                    $journalCount++;
                    $this->line("  #{$e->id} jurnal {$journal->reference} Rp ".number_format($journal->total_amount, 0, ',', '.'));
                }

                if (! $ledger->isInSync($e->refresh()->load('program'))) {
                    $stillBad[] = "#{$e->id} target=".json_encode($ledger->targetPosition($e))
                        .' posted='.json_encode($ledger->postedPosition($e));
                }

                $apply ? DB::commit() : DB::rollBack();
            } catch (\Throwable $ex) {
                DB::rollBack();
                $failed[] = "#{$id}: {$ex->getMessage()}";
            }
        }

        $this->newLine();
        $this->info(($apply ? '' : '[DRY-RUN] ')."Perubahan operasional: {$opCount} | Jurnal penyesuaian: {$journalCount}");

        if ($failed) {
            $this->warn('Perlu penanganan manual ('.count($failed).') — dilewati:');
            foreach ($failed as $f) {
                $this->line("  {$f}");
            }
        }

        if ($stillBad) {
            $this->error('Masih belum sinkron ('.count($stillBad).'):');
            foreach ($stillBad as $b) {
                $this->line("  {$b}");
            }

            return self::FAILURE;
        }

        $tb = DB::table('journal_items')->selectRaw('ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c')->first();
        $this->info('Enrollment yang bisa disinkronkan sudah sinkron. Trial balance: debit '.$tb->d.' vs credit '.$tb->c
            .' (selisih '.round((float) $tb->d - (float) $tb->c, 2).')');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
