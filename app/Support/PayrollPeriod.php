<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Periode honor tutor per pertemuan (aturan tetap Just Speak): payroll bulan X
 * menghitung pertemuan tanggal 26 bulan sebelumnya s.d. tanggal 25 bulan X.
 * Pertemuan tanggal 26 ke atas masuk gaji bulan berikutnya.
 *
 * Contoh: payroll Oktober 2026 = pertemuan 26 Sep – 25 Okt 2026.
 *
 * Gaji bulanan tutor tetap TIDAK ikut aturan ini — tetap per bulan kalender.
 */
class PayrollPeriod
{
    public const CUTOFF_DAY = 25;

    /**
     * Rentang tanggal pertemuan (inklusif) untuk payroll bulan $month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function forMonth(CarbonInterface|string $month): array
    {
        $m = Carbon::parse($month)->startOfMonth();

        return [
            $m->copy()->subMonthNoOverflow()->day(self::CUTOFF_DAY + 1)->startOfDay(),
            $m->copy()->day(self::CUTOFF_DAY)->startOfDay(),
        ];
    }

    /** Bulan payroll (tanggal 1) tempat pertemuan bertanggal $date dibayar. */
    public static function monthFor(CarbonInterface|string $date): Carbon
    {
        $d = Carbon::parse($date);
        $month = $d->copy()->startOfMonth();

        return $d->day > self::CUTOFF_DAY ? $month->addMonthNoOverflow() : $month;
    }

    /** Label periode, mis. "26 Sep – 25 Okt 2026". */
    public static function label(CarbonInterface|string $month): string
    {
        [$from, $to] = self::forMonth($month);

        return $from->translatedFormat('j M').' – '.$to->translatedFormat('j M Y');
    }
}
