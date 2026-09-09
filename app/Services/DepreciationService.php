<?php

namespace App\Services;

use App\Models\AdjustingJournal;
use App\Models\AdjustingJournalItem;
use App\Models\FixedAsset;
use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penyusutan aset tetap (garis lurus). Satu pintu untuk:
 *   - generator bulanan terjadwal (finance:generate-ajp), dan
 *   - membangun ulang seluruh jurnal penyusutan sebuah aset dari nol saat
 *     nilainya dikoreksi (harga/nilai residu/masa manfaat/tanggal perolehan).
 *
 * Setiap entri = satu AdjustingJournal (type "depreciation") yang langsung
 * diposting ke `journals`. Idempoten per (aset, bulan).
 */
class DepreciationService
{
    /**
     * Generate + post penyusutan untuk SATU periode (akhir bulan) untuk semua
     * aset aktif. Idempoten — bulan yang sudah ada dilewati.
     *
     * @return string[] baris log
     */
    public function generateForPeriod(Carbon $period): array
    {
        $period = $period->copy()->endOfMonth();
        $log = [];

        DB::transaction(function () use ($period, &$log) {
            $assets = FixedAsset::where('is_active', true)->where('useful_life', '>', 0)->get();

            foreach ($assets as $asset) {
                $line = $this->postMonth($asset, $period);
                if ($line !== null) {
                    $log[] = $line;
                }
            }
        });

        return $log;
    }

    /**
     * Hapus SEMUA jurnal penyusutan aset ini lalu buat ulang dari tanggal
     * perolehan sampai bulan berjalan (atau sampai habis masa manfaat), pakai
     * nilai aset yang sekarang. Dipakai saat aset dikoreksi.
     */
    public function rebuildAsset(FixedAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $ajs = AdjustingJournal::where('source_id', $asset->id)
                ->where('source_type', FixedAsset::class)
                ->where('type', 'depreciation')
                ->get();

            $journalIds = $ajs->pluck('posted_journal_id')->filter()->values();
            if ($journalIds->isNotEmpty()) {
                DB::table('journal_items')->whereIn('journal_id', $journalIds)->delete();
                Journal::whereIn('id', $journalIds)->delete();
            }
            AdjustingJournalItem::whereIn('adjusting_journal_id', $ajs->pluck('id'))->delete();
            AdjustingJournal::whereIn('id', $ajs->pluck('id'))->delete();

            if (! $asset->is_active || $asset->useful_life <= 0) {
                return;
            }
            if (! $asset->expense_account_id || ! $asset->accumulated_account_id) {
                return;
            }
            if ($asset->monthly_depreciation <= 0) {
                return;
            }

            $nowEom = now()->endOfMonth();
            for ($k = 0; $k < $asset->useful_life; $k++) {
                $period = $asset->acquired_at->copy()->startOfMonth()->addMonthsNoOverflow($k)->endOfMonth();
                if ($period->greaterThan($nowEom)) {
                    break;
                }
                $this->postMonth($asset, $period);
            }
        });
    }

    /**
     * Post satu bulan penyusutan untuk satu aset. Mengembalikan baris log, atau
     * null kalau dilewati (sudah ada / sudah habis masa manfaat / akun kosong).
     */
    private function postMonth(FixedAsset $asset, Carbon $period): ?string
    {
        $period = $period->copy()->endOfMonth();

        // Sudah lewat masa manfaat pada periode ini? (hitung historis, bukan now()
        // — supaya rebuild --period masa lalu tetap benar).
        if ((int) $asset->acquired_at->diffInMonths($period) >= $asset->useful_life) {
            return null;
        }
        // Periode sebelum bulan perolehan tidak menyusut apa pun.
        if ($period->lessThan($asset->acquired_at->copy()->endOfMonth())) {
            return null;
        }

        if (! $asset->expense_account_id || ! $asset->accumulated_account_id) {
            return null;
        }

        // Bulan ke-berapa sejak perolehan (0 = bulan perolehan).
        $monthIndex = (int) $asset->acquired_at->copy()->startOfMonth()
            ->diffInMonths($period->copy()->startOfMonth());

        $amount = $this->depreciationForMonth($asset, $monthIndex);
        if ($amount <= 0) {
            return null;
        }

        $exists = AdjustingJournal::where('source_id', $asset->id)
            ->where('source_type', FixedAsset::class)
            ->where('type', 'depreciation')
            ->whereYear('period', $period->year)
            ->whereMonth('period', $period->month)
            ->exists();
        if ($exists) {
            return null;
        }

        $periodStr = $period->toDateString();
        $aj = AdjustingJournal::create([
            'period' => $periodStr,
            'reference' => AdjustingJournal::generateReference($periodStr),
            'description' => "Penyusutan: {$asset->name}",
            'type' => 'depreciation',
            'status' => 'draft',
            'source_id' => $asset->id,
            'source_type' => FixedAsset::class,
            'total_amount' => $amount,
        ]);

        AdjustingJournalItem::insert([
            [
                'adjusting_journal_id' => $aj->id,
                'account_id' => $asset->expense_account_id,
                'debit' => $amount, 'credit' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'adjusting_journal_id' => $aj->id,
                'account_id' => $asset->accumulated_account_id,
                'debit' => 0, 'credit' => $amount,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        if (! Journal::where('reference', $aj->reference)->exists()) {
            $journal = Journal::create([
                'date' => $aj->period,
                'description' => "[AJP] {$aj->description}",
                'reference' => $aj->reference,
                'total_amount' => $aj->total_amount,
                'type' => 'adjusting',
            ]);
            foreach ($aj->fresh()->items as $item) {
                $journal->items()->create([
                    'account_id' => $item->account_id,
                    'debit' => $item->debit,
                    'credit' => $item->credit,
                ]);
            }
            $aj->update(['status' => 'posted', 'posted_journal_id' => $journal->id]);
        }

        return "Penyusutan {$asset->name} ({$period->format('M Y')}): Rp ".number_format($amount, 0, ',', '.');
    }

    /**
     * Beban penyusutan garis-lurus untuk satu bulan (index 0..useful_life-1).
     * Dibulatkan ke 2 desimal; BULAN TERAKHIR menyerap sisa pembulatan supaya
     * total akumulasi penyusutan == (cost − salvage) PERSIS (book value akhir
     * tepat di salvage value, tidak lebih/kurang sepersekian sen).
     */
    public function depreciationForMonth(FixedAsset $asset, int $monthIndex): float
    {
        $life = (int) $asset->useful_life;
        $base = round((float) $asset->cost - (float) $asset->salvage_value, 2);

        if ($life <= 0 || $base <= 0 || $monthIndex < 0 || $monthIndex >= $life) {
            return 0.0;
        }

        $perMonth = round($base / $life, 2);

        return $monthIndex === $life - 1
            ? round($base - $perMonth * ($life - 1), 2)
            : $perMonth;
    }
}
