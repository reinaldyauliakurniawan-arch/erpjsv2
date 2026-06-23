<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency middleware for unsafe HTTP methods (POST/PUT/PATCH/DELETE).
 *
 * Strategy:
 *   - Reads the `Idempotency-Key` request header (RFC draft).
 *   - If the key was seen within the TTL window, returns the cached response
 *     with the SAME status code and body — guaranteeing that retrying a
 *     request (network blip, double-click) produces identical side effects.
 *   - If no key is provided, the request passes through unchanged. This
 *     middleware is opt-in per endpoint (apply via route middleware alias).
 *
 * Usage in routes.php:
 *   Route::post('/finance/journals', [JournalController::class, 'store'])
 *       ->middleware('idempotent:journals.store,300');  // key prefix, ttl seconds
 *
 * Or apply globally to a group:
 *   Route::middleware('idempotent')->group(function () {
 *       Route::post('/payments/charge', ...);
 *   });
 *
 * Notes:
 *   - Cache store is configurable via the $ttl parameter and uses the default
 *     CACHE_STORE. For multi-instance deployments, use Redis/database cache
 *     so all instances share the same idempotency store.
 *   - The cached response preserves status, body, and Content-Type, but not
 *     headers like Set-Cookie (to avoid stale session state).
 *   - Failed responses (5xx) are NOT cached — the client should retry.
 */
class IdempotencyMiddleware
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function handle(Request $request, Closure $next, ?string $keyPrefix = null, int $ttl = 300): Response
    {
        // Only enforce idempotency on unsafe methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // No key provided → request passes through (opt-in middleware)
        if (!$idempotencyKey || !is_string($idempotencyKey) || $idempotencyKey === '') {
            return $next($request);
        }

        // Build a namespaced cache key:
        // idempotent:{prefix}:{route}:{key}
        // The route name/URI is included to prevent key reuse across endpoints
        // (a key valid for /payments/charge should not return cached /users/delete).
        $routeName = $request->route()?->getName() ?? $request->path();
        $cacheKey  = $this->buildCacheKey($keyPrefix, $routeName, $idempotencyKey);

        // Use lock to prevent thundering herd when two identical requests arrive
        // simultaneously — the first acquires the lock and processes; the second
        // waits briefly and then either reads the cached result or proceeds.
        $lock = $this->cache->lock("{$cacheKey}:lock", 10);

        try {
            if (!$lock->block(5)) {
                // Could not acquire lock within 5 seconds — another request is in flight.
                // Return 409 to signal the client to retry.
                return response()->json([
                    'error'   => 'idempotency_conflict',
                    'message' => 'Another request with the same Idempotency-Key is currently being processed.',
                ], 409);
            }

            // Check if a response was cached for this key
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $this->reconstructResponse($cached);
            }

            // Process the request
            $response = $next($request);

            // Only cache successful responses (2xx) and client errors (4xx).
            // 5xx responses should NOT be cached — client is expected to retry.
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 500) {
                $this->cache->put($cacheKey, $this->serializeResponse($response), $ttl);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function buildCacheKey(?string $prefix, string $route, string $key): string
    {
        $parts = array_filter(['idempotent', $prefix, $route, $key]);
        return implode(':', $parts);
    }

    private function serializeResponse(Response $response): array
    {
        return [
            'status'  => $response->getStatusCode(),
            'content' => $response->getContent(),
            'headers' => $this->selectCacheableHeaders($response),
        ];
    }

    private function reconstructResponse(array $cached): Response
    {
        $response = response($cached['content'], $cached['status']);

        foreach ($cached['headers'] ?? [] as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Mark the response so the client knows it came from idempotency cache
        $response->headers->set('X-Idempotent-Replay', 'true');

        return $response;
    }

    /**
     * Only preserve Content-Type and a small whitelist of headers.
     * Skip Set-Cookie (session state could have changed) and other
     * dynamic headers to avoid replaying stale state.
     */
    private function selectCacheableHeaders(Response $response): array
    {
        $allowed = ['Content-Type', 'X-Total-Count', 'X-Request-Id'];
        $kept    = [];

        foreach ($allowed as $name) {
            if ($response->headers->has($name)) {
                $kept[$name] = $response->headers->get($name);
            }
        }

        return $kept;
    }
}
