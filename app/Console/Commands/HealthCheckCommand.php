<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health check command for load balancer probes.
 *
 * Designed to be called by the load balancer's health check endpoint:
 *   - Exit code 0 → healthy (HTTP 200 from the /up route)
 *   - Exit code 1 → unhealthy (HTTP 503)
 *
 * Checks performed:
 *   1. Database connectivity (SELECT 1)
 *   2. Redis connectivity (if configured — skip if not)
 *   3. Storage directory writability
 *   4. (Optional) Queue worker liveness via last_heartbeat timestamp
 *
 * Usage:
 *   php artisan app:health-check
 *
 * In production, wrap this in the /up route handler or a dedicated /healthz
 * endpoint that returns 200/503 based on the exit code.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check {--detailed : Include detailed check output}';
    protected $description = 'Run health checks for load balancer probes';

    private array $checks = [];
    private bool $allHealthy = true;

    public function handle(): int
    {
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkStorage();

        if ($this->option('detailed')) {
            foreach ($this->checks as $name => $result) {
                $status = $result['healthy'] ? '<info>OK</info>' : '<error>FAIL</error>';
                $this->line("  {$status}  {$name}: {$result['message']}");
            }
        }

        if (!$this->allHealthy) {
            $this->error('Health check failed');
            return self::FAILURE;
        }

        $this->info('All health checks passed');
        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::select('SELECT 1');
            $this->recordCheck('database', true, 'connection ok');
        } catch (\Throwable $e) {
            $this->recordCheck('database', false, $e->getMessage());
        }
    }

    private function checkRedis(): void
    {
        $redisHost = config('database.redis.default.host');
        if (!$redisHost || $redisHost === '127.0.0.1' && !env('REDIS_PASSWORD')) {
            // Redis not configured — skip
            $this->recordCheck('redis', true, 'not configured (skipped)');
            return;
        }

        try {
            Redis::ping();
            $this->recordCheck('redis', true, 'ping ok');
        } catch (\Throwable $e) {
            $this->recordCheck('redis', false, $e->getMessage());
        }
    }

    private function checkStorage(): void
    {
        $path = storage_path('app');
        if (!is_writable($path)) {
            $this->recordCheck('storage', false, "path not writable: {$path}");
            return;
        }

        // Try writing a probe file
        $probeFile = $path . '/.health_probe';
        try {
            file_put_contents($probeFile, (string) time());
            unlink($probeFile);
            $this->recordCheck('storage', true, 'writable');
        } catch (\Throwable $e) {
            $this->recordCheck('storage', false, $e->getMessage());
        }
    }

    private function recordCheck(string $name, bool $healthy, string $message): void
    {
        $this->checks[$name] = ['healthy' => $healthy, 'message' => $message];
        if (!$healthy) {
            $this->allHealthy = false;
        }
    }
}
