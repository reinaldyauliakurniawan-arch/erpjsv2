<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request observability middleware.
 *
 * Records every HTTP request as a structured log entry with:
 *   - method, URL, status code
 *   - duration in milliseconds
 *   - user ID (if authenticated)
 *   - error classification (4xx vs 5xx)
 *
 * This log is in JSON format so OpenObserve can ingest it directly
 * via its log pipeline. When OTEL is enabled, the auto-instrumentation
 * package creates spans with the same data — this middleware is a
 * fallback that works even without OTEL (just structured logging).
 *
 * Usage in bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->append(\App\Http\Middleware\ObservabilityMiddleware::class);
 *   })
 *
 * OpenObserve log query examples:
 *   - Error count last 5m:
 *     SELECT count(*) FROM logs WHERE level='error' AND timestamp > now() - 5m
 *   - P95 latency:
 *     SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) FROM logs
 *   - Error rate:
 *     SELECT count(CASE WHEN status >= 500 THEN 1 END) / count(*) * 100 AS error_rate
 *     FROM logs WHERE timestamp > now() - 5m
 */
class ObservabilityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $this->logRequest($request, $response, $durationMs);

        // Add latency header for load balancer / monitoring probes
        $response->headers->set('X-Response-Time-ms', (string) $durationMs);

        return $response;
    }

    /**
     * Log the request as structured JSON.
     *
     * Error classification:
     *   - 5xx → error level (server error, needs investigation)
     *   - 4xx → warning level (client error, might indicate a bug)
     *   - 3xx → info level (redirect, normal)
     *   - 2xx → info level (success)
     */
    private function logRequest(Request $request, Response $response, int $durationMs): void
    {
        $status = $response->getStatusCode();
        $level  = $this->levelForStatus($status);

        $context = [
            'http.method'        => $request->method(),
            'http.url'           => $request->fullUrl(),
            'http.path'          => $request->path(),
            'http.status_code'   => $status,
            'http.duration_ms'   => $durationMs,
            'http.user_agent'    => $request->userAgent(),
            'http.remote_addr'   => $request->ip(),
            'service.name'       => config('opentelemetry.service.name', 'erp-just-speak'),
            'deployment.env'     => config('app.env'),
        ];

        // Add user context if authenticated
        $user = $request->user();
        if ($user) {
            $context['user.id']    = $user->id;
            $context['user.role']  = $user->role ?? 'unknown';
        }

        // Add error details for 5xx
        if ($status >= 500) {
            $exception = $response->exception;
            if ($exception) {
                $context['error.type']     = $exception::class;
                $context['error.message']  = $exception->getMessage();
                $context['error.file']     = $exception->getFile() . ':' . $exception->getLine();
            }
        }

        Log::$level('http.request', $context);
    }

    private function levelForStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            $status >= 300 => 'info',
            default        => 'info',
        };
    }
}
