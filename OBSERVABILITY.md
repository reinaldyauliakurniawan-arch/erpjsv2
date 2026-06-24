# Observability Setup — OpenObserve + OpenTelemetry

This guide walks you through setting up observability for the ERP Just Speak
app using OpenObserve as the observability backend and OpenTelemetry (OTEL)
as the instrumentation layer.

## Architecture

```
┌──────────────────┐     OTEL/HTTP      ┌──────────────────┐
│  ERP Just Speak  │ ──────────────────► │   OpenObserve    │
│  (Laravel app)   │   traces + logs     │  (port 5080)     │
│                  │                     │                  │
│  Observability   │   JSON log pipeline │  Dashboards      │
│  Middleware      │ ──────────────────► │  Traces          │
│                  │                     │  Logs            │
│  OTEL Auto-Instr │                     │  Alerts          │
│  (DB, HTTP, etc) │                     │                  │
└──────────────────┘                     └──────────────────┘
```

## Quick Start

### 1. Install OpenObserve

```bash
# Download and run OpenObserve
curl -L https://github.com/openobserve/openobserve/releases/latest/download/openobserve-linux-amd64.tar.gz | tar xz
./openobserve

# Or via Docker:
# docker run -d -p 5080:5080 -e ZO_ROOT_USER_EMAIL="root@example.com" \
#   -e ZO_ROOT_USER_PASSWORD="Complexpass#123" \
#   -v ./data:/data openobserve/openobserve:latest
```

OpenObserve starts on `http://localhost:5080`.
Login: `root@example.com` / `Complexpass#123`

### 2. Configure the ERP app

Copy the OTEL settings to your `.env`:

```bash
# Enable OpenTelemetry
OTEL_ENABLED=true

# Point to OpenObserve's OTLP endpoint
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:5080/api/default/traces

# Auth header (pre-encoded for default OpenObserve credentials)
OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic cm9vdEBleGFtcGxlLmNvbTpDb21wbGV4cGFzcyMxMjM=

# Service name (appears in OpenObserve as the top-level entity)
OTEL_SERVICE_NAME=erp-just-speak

# Sampling: 1.0 = 100% of traces exported (good for dev)
OTEL_SAMPLER_RATIO=1.0
```

### 3. Install OTEL packages

```bash
composer install
# This pulls: open-telemetry/sdk, open-telemetry/exporter-otlp,
# open-telemetry/opentelemetry-auto-laravel
```

### 4. Run the app

```bash
php artisan serve
# Or: composer dev
```

### 5. Generate traffic

Open `http://localhost:8000` and click around — login, view dashboards, etc.
Each request generates a trace + structured log entry.

### 6. View in OpenObserve

- **Traces**: `http://localhost:5080/web/traces`
  - Service: `erp-just-speak`
  - You'll see HTTP request spans with method, URL, status, duration
  - DB query spans (SELECT, INSERT, UPDATE) with the actual SQL
  - Waterfall view showing where time is spent

- **Logs**: `http://localhost:5080/web/logs`
  - Stream: `default`
  - Each `http.request` log entry includes: method, path, status, duration_ms, user_id, user_role
  - Filter by `level=error` to see only errors
  - Filter by `http.status_code>=500` for server errors

## Agent Rules (Post-Deploy Checklist)

After deploying the app, run this checklist:

```bash
# Check the last 5 minutes of logs
php artisan app:observability-check --minutes=5
```

This command checks 3 golden signals and exits with:

| Exit code | Meaning | Action |
|-----------|---------|--------|
| 0 | All clear | Ship it |
| 1 | Warnings (errors > 0 OR p95 > 500ms) | Investigate + fix |
| 2 | Critical (error rate > 1%) | **Rollback** |

### Rule 1: Error count > 0 → fix

If the command finds any 5xx errors:

```
⚠️  WARNING: 3 server error(s) found in the last 5 minute(s).
   Agent rule: investigate and fix.

Error details:
  [2025-06-24T15:32:01Z] POST /finance/journals → 500 (1234ms) BalanceMismatchException
  [2025-06-24T15:33:15Z] GET /admin/dashboard → 500 (567ms) RuntimeException
```

Fix the error, redeploy, re-check.

### Rule 2: P95 latency > 500ms → optimize

If p95 exceeds 500ms:

```
⚠️  WARNING: P95 latency 678ms exceeds 500ms threshold.
   Agent rule: optimize bottleneck.

Slowest requests:
  GET /admin/dashboard → 200 (1234ms)
  GET /finance/reports/general-ledger → 200 (987ms)
  POST /admin/enrollments → 302 (845ms)
```

Common optimizations:
- Add `with()` eager loading for N+1 queries (OTEL DB spans show query count)
- Add pagination to `->get()` calls
- Cache expensive report queries
- Check for missing DB indexes (see migration `2026_06_23_000001_add_composite_indexes`)

### Rule 3: Error rate > 1% → rollback

If error rate exceeds 1%:

```
⚠️  CRITICAL: Error rate 2.5% exceeds 1% threshold.
   Agent rule: rollback recommended.
```

Rollback steps:
```bash
git revert HEAD           # revert the last commit
php artisan migrate:rollback  # rollback any new migrations
php artisan config:clear
php artisan cache:clear
# Verify the rollback fixed the error rate
php artisan app:observability-check --minutes=5
```

## What Gets Instrumented

### Automatic (OTEL Auto-Instrumentation)

The `open-telemetry/opentelemetry-auto-laravel` package automatically
instruments:

| Layer | What's captured |
|-------|----------------|
| HTTP | Method, URL, status, duration, headers |
| Database | Connection, query, bindings, duration |
| Cache | Get/set/forget operations |
| Queue | Job dispatch + processing |
| Redis | All commands |

No code changes needed — just install the package and set `OTEL_ENABLED=true`.

### Manual (ObservabilityMiddleware)

The `app/Http/Middleware/ObservabilityMiddleware.php` adds:

- Structured JSON log entry for every request
- `X-Response-Time-ms` response header (for LB probes)
- User context (ID, role) on every log entry
- Error classification (5xx → error, 4xx → warning)

This works **even without OTEL** — the structured logs are written to
`storage/logs/laravel.log` in JSON format that OpenObserve can ingest
via its log pipeline.

## OpenObserve Alert Setup

Configure alerts in OpenObserve's UI (`http://localhost:5080/web/alerts`):

### Alert 1: Error Spike
```sql
SELECT count(*) FROM logs
WHERE level = 'error'
  AND timestamp > now() - 5m
HAVING count(*) > 0
```
Action: Send webhook / email

### Alert 2: High Latency
```sql
SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY http_duration_ms) AS p95
FROM logs
WHERE timestamp > now() - 5m
HAVING p95 > 500
```
Action: Send webhook / email

### Alert 3: Error Rate
```sql
SELECT
  count(CASE WHEN http_status_code >= 500 THEN 1 END) * 100.0 / count(*) AS error_rate
FROM logs
WHERE timestamp > now() - 5m
HAVING error_rate > 1.0
```
Action: Send webhook / trigger rollback

## Troubleshooting

### No traces appearing in OpenObserve

1. Verify OpenObserve is running: `curl http://localhost:5080/healthz`
2. Check `OTEL_ENABLED=true` in `.env`
3. Check `OTEL_EXPORTER_OTLP_ENDPOINT` points to the correct stream
4. Verify auth header is correct:
   ```bash
   echo -n "root@example.com:Complexpass#123" | base64
   # Should output: cm9vdEBleGFtcGxlLmNvbTpDb21wbGV4cGFzcyMxMjM=
   ```
5. Check Laravel log for OTEL exporter errors: `grep -i "otel\|opentelemetry" storage/logs/laravel.log`

### High overhead from tracing

Reduce sampling ratio:
```env
OTEL_SAMPLER_RATIO=0.1  # Only 10% of traces exported
```

Disable specific instrumentation:
```env
OTEL_INSTRUMENT_REDIS=false  # Skip Redis traces if not needed
OTEL_INSTRUMENT_CACHE=false  # Skip cache traces
```

### Log file growing too fast

The ObservabilityMiddleware logs every request. For high-traffic apps,
use daily log rotation (already configured in `config/logging.php`):
```env
LOG_STACK=daily
LOG_DAILY_DAYS=7  # Keep 7 days of logs
```

Or send logs directly to OpenObserve via its log ingestion API instead
of writing to file (see OpenObserve docs for syslog/Fluentd integration).
