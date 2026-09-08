<?php

namespace App\Console\Commands;

use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyAdjustingJournals extends Command
{
    protected $signature = 'finance:generate-ajp {--period= : Y-m-d format, default akhir bulan ini}';

    protected $description = 'Generate dan post jurnal penyesuaian depresiasi aset tetap otomatis';

    public function handle(DepreciationService $depreciation): void
    {
        $period = $this->option('period')
            ? Carbon::parse($this->option('period'))->endOfMonth()
            : Carbon::now()->endOfMonth();

        $this->info("Generating AJP untuk periode: {$period->toDateString()}");

        foreach ($depreciation->generateForPeriod($period) as $line) {
            $this->line('  ✓ '.$line);
        }

        $this->info('Selesai.');
    }
}
