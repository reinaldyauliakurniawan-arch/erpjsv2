<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the IdempotencyMiddleware.
 *
 * Verifies that:
 *   - Same Idempotency-Key returns the cached response on retry
 *   - Different keys produce different responses
 *   - GET requests bypass the middleware entirely
 *   - 5xx responses are NOT cached (client should retry)
 *   - 4xx responses ARE cached (client errors are deterministic)
 */
class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define test routes that use the middleware
        Route::post('/_test/idempotent', function (Request $request) {
            return response()->json([
                'ok'      => true,
                'counter' => app()->bound('test.counter') ? app('test.counter') : 0,
            ]);
        })->middleware(IdempotencyMiddleware::class);

        Route::post('/_test/idempotent-fail', function (Request $request) {
            return response()->json(['error' => 'something went wrong'], 500);
        })->middleware(IdempotencyMiddleware::class);

        Route::post('/_test/idempotent-validation', function (Request $request) {
            return response()->json(['error' => 'bad input'], 422);
        })->middleware(IdempotencyMiddleware::class);
    }

    #[Test]
    public function get_requests_bypass_middleware(): void
    {
        // GET requests should pass through — no idempotency needed
        Route::get('/_test/idempotent-get', fn () => response('ok'))
            ->middleware(IdempotencyMiddleware::class);

        $this->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->get('/_test/idempotent-get')
            ->assertOk();
    }

    #[Test]
    public function post_without_key_passes_through(): void
    {
        $this->post('/_test/idempotent')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    #[Test]
    public function same_key_returns_same_response_on_retry(): void
    {
        $key = 'test-key-' . uniqid();

        // First request
        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent')
            ->assertOk();

        $firstBody = $first->json();

        // Second request with same key — should return cached response
        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent')
            ->assertOk();

        $this->assertSame($firstBody, $second->json(), 'Retry should return identical response');

        // Response should be marked as a replay
        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
    }

    #[Test]
    public function different_keys_produce_different_responses(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'key-A'])
            ->post('/_test/idempotent')
            ->assertOk();

        $this->withHeaders(['Idempotency-Key' => 'key-B'])
            ->post('/_test/idempotent')
            ->assertOk()
            ->assertHeaderMissing('X-Idempotent-Replay');
    }

    #[Test]
    public function server_error_responses_are_not_cached(): void
    {
        $key = 'fail-key-' . uniqid();

        // First request — returns 500
        $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent-fail')
            ->assertStatus(500);

        // Second request with same key — should NOT return cached 500.
        // Instead, it should execute the handler again (returning 500 again,
        // but the response is fresh, not cached).
        $response = $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent-fail')
            ->assertStatus(500);

        $this->assertNull($response->headers->get('X-Idempotent-Replay'));
    }

    #[Test]
    public function client_error_responses_are_cached(): void
    {
        $key = 'val-key-' . uniqid();

        // First request — returns 422
        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent-validation')
            ->assertStatus(422);

        // Second request with same key — should return cached 422
        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->post('/_test/idempotent-validation')
            ->assertStatus(422);

        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
        $this->assertSame($first->json(), $second->json());
    }
}
