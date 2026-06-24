<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Post-deploy observability check.
 *
 * Implements the agent rules:
 *   1. Deploy → check error log last 5 minutes
 *   2. Error count > 0 → investigate + fix
 *   3. Latency p95 > 500ms → optimize bottleneck
 *   4. Error rate > 1% → rollback
 *
 * This command reads the local Laravel log file (which the
 * ObservabilityMiddleware writes structured JSON entries to) and
 * computes the 3 golden signals: error count, p95 latency, error rate.
 *
 * When OpenObserve is running, the same data is available via its
 * query API — but this command works without OpenObserve by reading
 * the local log directly.
 *
 * Usage:
 *   php artisan app:observability-check
 *   php artisan app:observability-check --minutes=15
 *
 * Exit codes:
 *   0 = all clear (no errors, p95 < 500ms, error rate < 1%)
 *   1 = warnings (errors found or latency high — investigate)
 *   2 = critical (error rate > 1% — rollback recommended)
 */
class ObservabilityCheckCommand extends Command
{
    protected $signature = 'app:observability-check {--minutes=5 : Look-back window in minutes}';
    protected $description = 'Check error count, p95 latency, and error rate from recent logs';

    private const LATENCY_THRESHOLD_MS = 500;
    private const ERROR_RATE_THRESHOLD  = 0.01; // 1%

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $logPath = storage_path('logs/laravel.log');
        if (!File::exists($logPath)) {
            $this->info('No log file found — app may not have received any requests yet.');
            return 0;
        }

        // Parse structured JSON log entries from the last N minutes
        $entries = $this->parseRecentLogs($logPath, $cutoff);

        if ($entries->isEmpty()) {
            $this->info("No http.request log entries found in the last {$minutes} minute(s).");
            $this->info('Make a few requests to the app, then re-run this command.');
            return 0;
        }

        $total      = $entries->count();
        $errors     = $entries->filter(fn ($e) => ($e['http.status_code'] ?? 0) >= 500)->count();
        $errorRate  = $total > 0 ? $errors / $total : 0;
        $latencies  = $entries->pluck('http.duration_ms')->filter()->sort()->values();
        $p95        = $this->percentile($latencies, 0.95);

        $this->info("=== Observability Check (last {$minutes} min) ===");
        $this->table(['Metric', 'Value', 'Threshold', 'Status'], [
            ['Total requests',  $total,                   '—',                'OK'],
            ['Server errors',   $errors,                  '> 0',              $errors > 0 ? 'WARN' : 'OK'],
            ['Error rate',      number_format($errorRate * 100, 2) . '%',    '> 1%',  $errorRate > self::ERROR_RATE_THRESHOLD ? 'CRITICAL' : 'OK'],
            ['P95 latency',     $p95 . ' ms',             '> 500 ms',         $p95 > self::LATENCY_THRESHOLD_MS ? 'WARN' : 'OK'],
        ]);

        // Agent rule: error rate > 1% → rollback
        if ($errorRate > self::ERROR_RATE_THRESHOLD) {
            $this->error("\n⚠️  CRITICAL: Error rate " . number_format($errorRate * 100, 2) . "% exceeds 1% threshold.");
            $this->error('   Agent rule: rollback recommended.');

            $this->showErrorDetails($entries);
            return 2;
        }

        // Agent rule: errors > 0 → fix
        if ($errors > 0) {
            $this->warn("\n⚠️  WARNING: {$errors} server error(s) found in the last {$minutes} minute(s).");
            $this->warn('   Agent rule: investigate and fix.');

            $this->showErrorDetails($entries);
            return 1;
        }

        // Agent rule: p95 > 500ms → optimize
        if ($p95 > self::LATENCY_THRESHOLD_MS) {
            $this->warn("\n⚠️  WARNING: P95 latency {$p95}ms exceeds 500ms threshold.");
            $this->warn('   Agent rule: optimize bottleneck.');

            $this->showSlowRequests($entries, $p95);
            return 1;
        }

        $this->info("\n✓ All clear — no errors, p95 within threshold, error rate below 1%.");
        return 0;
    }

    /**
     * Parse the Laravel log file and extract structured http.request entries
     * from the last N minutes.
     */
    private function parseRecentLogs(string $path, $cutoff): \Illuminate\Support\Collection
    {
        $entries = collect();
        $content = File::get($path);

        // Each log line starts with a timestamp like [2025-06-24 15:30:00]
        // followed by the log level and JSON context.
        // Example: [2025-06-24 15:30:00] local.INFO: http.request {"http.method":"GET",...}
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\w+\.(\w+):\s+http\.request\s+({.*})/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $timestamp = \Carbon\Carbon::parse($m[1]);
            if ($timestamp < $cutoff) {
                continue;
            }

            $data = json_decode($m[3], true);
            if (is_array($data)) {
                $data['_timestamp'] = $timestamp->toIso8601String();
                $entries->push($data);
            }
        }

        return $entries;
    }

    /**
     * Compute the Nth percentile of a sorted collection.
     */
    private function percentile(\Illuminate\Support\Collection $sorted, float $p): int
    {
        if ($sorted->isEmpty()) {
            return 0;
        }
        $index = (int) ceil($p * $sorted->count()) - 1;
        return (int) $sorted->get(max(0, $index));
    }

    /**
     * Show details of server error requests.
     */
    private function showErrorDetails(\Illuminate\Support\Collection $entries): void
    {
        $errors = $entries->filter(fn ($e) => ($e['http.status_code'] ?? 0) >= 500);
        $this->line("\nError details:");
        foreach ($errors as $e) {
            $this->line(sprintf(
                '  [%s] %s %s → %d (%.0fms) %s',
                $e['_timestamp'] ?? '?',
                $e['http.method'] ?? '?',
                $e['http.path'] ?? '?',
                $e['http.status_code'] ?? 0,
                $e['http.duration_ms'] ?? 0,
                $e['error.message'] ?? ''
            ));
        }
    }

    /**
     * Show the slowest requests.
     */
    private function showSlowRequests(\Illuminate\Support\Collection $entries, int $p95): void
    {
        $slow = $entries->sortByDesc('http.duration_ms')->take(5);
        $this->line("\nSlowest requests:");
        foreach ($slow as $e) {
            $this->line(sprintf(
                '  %s %s → %d (%.0fms)',
                $e['http.method'] ?? '?',
                $e['http.path'] ?? '?',
                $e['http.status_code'] ?? 0,
                $e['http.duration_ms'] ?? 0
            ));
        }
    }
}
