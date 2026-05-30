<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\GenerateMonthlyAdjustingJournals;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-expirations')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('SCHEDULER FAILED: app:check-expirations');
    });

Schedule::command('app:payment-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('SCHEDULER FAILED: app:payment-reminders');
    });

Schedule::command(GenerateMonthlyAdjustingJournals::class)
    ->monthlyOn(28, '23:59')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('SCHEDULER FAILED: finance:generate-ajp');
    });
