<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FixedAsset;
use App\Models\AdjustingJournal;
use App\Models\AdjustingJournalItem;
use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyAdjustingJournals extends Command
{
    protected $signature   = 'finance:generate-ajp {--period= : Y-m-d format, default akhir bulan ini}';
    protected $description = 'Generate dan post jurnal penyesuaian depresiasi aset tetap otomatis';

    public function handle(): void
    {
        $period = $this->option('period')
            ? Carbon::parse($this->option('period'))->endOfMonth()
            : Carbon::now()->endOfMonth();

        $periodStr = $period->toDateString();

        $this->info("Generating AJP untuk periode: {$periodStr}");

        DB::transaction(function () use ($period, $periodStr) {
            $this->generateDepreciation($period, $periodStr);
        });

        $this->info('Selesai.');
    }

    private function generateDepreciation(Carbon $period, string $periodStr): void
    {
        $assets = FixedAsset::where('is_active', true)
            ->where('useful_life', '>', 0)
            ->get();

        foreach ($assets as $asset) {
            if ($asset->status === 'fully_depreciated') continue;

            $exists = AdjustingJournal::where('source_id', $asset->id)
                ->where('source_type', FixedAsset::class)
                ->where('type', 'depreciation')
                ->whereYear('period', $period->year)
                ->whereMonth('period', $period->month)
                ->exists();

            if ($exists) continue;

            $amount = $asset->monthly_depreciation;
            if ($amount <= 0) continue;

            if (!$asset->expense_account_id || !$asset->accumulated_account_id) {
                $this->warn("  ⚠ Aset '{$asset->name}' belum punya akun depresiasi, skip.");
                continue;
            }

            $aj = AdjustingJournal::create([
                'period'       => $periodStr,
                'reference'    => AdjustingJournal::generateReference($periodStr),
                'description'  => "Penyusutan: {$asset->name}",
                'type'         => 'depreciation',
                'status'       => 'draft',
                'source_id'    => $asset->id,
                'source_type'  => FixedAsset::class,
                'total_amount' => $amount,
            ]);

            AdjustingJournalItem::insert([
                [
                    'adjusting_journal_id' => $aj->id,
                    'account_id'           => $asset->expense_account_id,
                    'debit'                => $amount,
                    'credit'               => 0,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
                [
                    'adjusting_journal_id' => $aj->id,
                    'account_id'           => $asset->accumulated_account_id,
                    'debit'                => 0,
                    'credit'               => $amount,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
            ]);

            $this->postToJournal($aj);
            $this->line("  ✓ Depresiasi: {$asset->name} — Rp " . number_format($amount, 0, ',', '.'));
        }
    }

    private function postToJournal(AdjustingJournal $aj): void
    {
        if (Journal::where('reference', $aj->reference)->exists()) {
            $this->warn("  ⚠ Journal {$aj->reference} sudah ada, skip.");
            return;
        }

        $journal = Journal::create([
            'date'         => $aj->period,
            'description'  => "[AJP] {$aj->description}",
            'reference'    => $aj->reference,
            'total_amount' => $aj->total_amount,
            'type'         => 'adjusting',
        ]);
        foreach ($aj->fresh()->items as $item) {
            $journal->items()->create([
                'account_id' => $item->account_id,
                'debit'      => $item->debit,
                'credit'     => $item->credit,
            ]);
        }
        $aj->update(['status' => 'posted', 'posted_journal_id' => $journal->id]);
    }
}
