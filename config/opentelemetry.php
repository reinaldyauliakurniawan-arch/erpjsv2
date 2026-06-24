<?php

declare(strict_types=1);

/**
 * OpenTelemetry configuration for OpenObserve integration.
 *
 * This config wires up the OTEL PHP SDK to export traces, metrics, and
 * logs to an OpenObserve instance running locally (or remotely).
 *
 * Setup:
 *   1. Install OpenObserve:
 *      curl -L https://github.com/openobserve/openobserve/releases/latest/download/openobserve-linux-amd64.tar.gz | tar xz && ./openobserve
 *   2. Login at http://localhost:5080 (root@example.com / Complexpass#123)
 *   3. Set these env vars in .env:
 *      OTEL_ENABLED=true
 *      OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:5080/api/default/traces
 *      OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic <base64(root@example.com:Complexpass#123)>
 *   4. Run `composer install` to pull the OTEL packages
 *   5. Start the app: `php artisan serve`
 *
 * What gets exported:
 *   - HTTP request spans (method, URL, status, duration)
 *   - DB query spans (connection, query, duration)
 *   - Redis operations (if Redis is configured)
 *   - Exception events (stack trace, message)
 *
 * OpenObserve dashboard:
 *   After starting the app and making a few requests, go to:
 *     http://localhost:5080/web/traces
 *   You'll see the service "erp-just-speak" with trace waterfall views.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, no traces/metrics/logs are exported.
    | Set OTEL_ENABLED=true in .env to activate.
    |
    */

    'enabled' => env('OTEL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Service Identity
    |--------------------------------------------------------------------------
    |
    | The service name appears as the top-level entity in OpenObserve.
    | All traces, metrics, and logs are attributed to this service.
    |
    */

    'service' => [
        'name'    => env('OTEL_SERVICE_NAME', 'erp-just-speak'),
        'version' => env('OTEL_SERVICE_VERSION', '1.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTEL Exporter (OTLP HTTP)
    |--------------------------------------------------------------------------
    |
    | The OTLP HTTP exporter sends trace data to OpenObserve's OTLP endpoint.
    |
    | Endpoint format for OpenObserve:
    |   http://localhost:5080/api/<stream_name>/traces
    |
    | The default stream is "default". You can create custom streams in
    | OpenObserve for different environments (dev, staging, prod).
    |
    | Headers must include the Authorization header for OpenObserve.
    | Format: Basic <base64(email:password)>
    | Generate with: echo -n "root@example.com:Complexpass#123" | base64
    |
    */

    'exporter' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:5080/api/default/traces'),
        'headers'  => env('OTEL_EXPORTER_OTLP_HEADERS', ''),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http'), // http | grpc
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Controls what percentage of traces are exported. 1.0 = 100% (all traces).
    | For production with high traffic, reduce to 0.1 (10%) to reduce overhead.
    | For development, keep at 1.0 to see every request.
    |
    */

    'sampling' => [
        'type'    => env('OTEL_SAMPLER_TYPE', 'always_on'), // always_on | always_off | trace_id_ratio
        'ratio'   => (float) env('OTEL_SAMPLER_RATIO', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Attributes
    |--------------------------------------------------------------------------
    |
    | Additional metadata attached to every span/metric/log. These appear
    | as filterable attributes in OpenObserve.
    |
    */

    'resource' => [
        'deployment.environment' => env('APP_ENV', 'local'),
        'host.name'              => gethostname(),
        'telemetry.sdk.name'     => 'opentelemetry',
        'telemetry.sdk.language' => 'php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Instrumentation
    |--------------------------------------------------------------------------
    |
    | The open-telemetry/opentelemetry-auto-laravel package automatically
    | instruments:
    |   - HTTP requests (middleware)
    |   - Database queries (Eloquent / query builder)
    |   - Cache operations
    |   - Queue jobs
    |   - Redis commands
    |
    | No code changes needed — just install the package and set OTEL_ENABLED=true.
    |
    */

    'auto_instrumentation' => [
        'http'       => env('OTEL_INSTRUMENT_HTTP', true),
        'db'         => env('OTEL_INSTRUMENT_DB', true),
        'cache'      => env('OTEL_INSTRUMENT_CACHE', true),
        'queue'      => env('OTEL_INSTRUMENT_QUEUE', true),
        'redis'      => env('OTEL_INSTRUMENT_REDIS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Thresholds (documentation only — enforced by OpenObserve)
    |--------------------------------------------------------------------------
    |
    | These values document the SLO thresholds the agent should check
    | after deploying. The actual alerting is configured in OpenObserve's
    | alert rules UI, but these values serve as the source of truth.
    |
    | Agent workflow:
    |   1. Deploy app
    |   2. Wait 5 minutes
    |   3. Check OpenObserve for errors in the last 5m
    |   4. If error count > 0 → investigate + fix
    |   5. Check p95 latency → if > 500ms → optimize bottleneck
    |   6. Check error rate → if > 1% → rollback
    |
    */

    'slo' => [
        'error_rate_threshold'    => 0.01,   // 1%
        'latency_p95_threshold_ms' => 500,   // 500ms
        'check_window_minutes'     => 5,     // 5-minute rolling window
    ],

];
