# Production Readiness Improvements

This document summarizes the production-grade improvements applied to the ERP Just Speak backend.

## Summary

This release addresses **Layer 1 (Correctness)**, **Layer 2 (Resilience)**, and **Layer 5 (Scalability)** of the production checklist. The work was deliberately scoped to **critical-path correctness** rather than chasing coverage numbers — every new test exercises a real business flow that, if broken, would cause financial or operational damage.

---

## Layer 1: Correctness

### Money value object (`app/ValueObjects/Money.php`)

Immutable, type-safe representation of monetary amounts. Solves the floating-point rounding problem that plagues financial applications by storing amounts in **minor units (integer)** rather than decimals.

**Why this matters:**
- `0.1 + 0.2 = 0.30000000000000004` in IEEE 754 floats. For an ERP that handles thousands of journal entries, this drift accumulates into balance mismatches.
- Existing code uses `bcdiv`/`bcmul` for some calculations and raw floats for others — inconsistent and error-prone.
- The `Money` class makes arithmetic explicit and forces callers to acknowledge currency mismatches.

**Features:**
- Parses Indonesian (`1.234.567,89`) and Western (`1,234,567.89`) number formats
- Arithmetic: `add`, `subtract`, `multiply`, `divide` — all return new instances
- Comparisons: `equals`, `greaterThan`, `lessThan`, `isZero`, `isPositive`, `isNegative`
- Conversions: `toMinorUnits`, `toDecimal`, `format` (renders as `Rp 1.234.567`)
- JSON serializable for API responses

**Test coverage:** 30+ unit tests in `tests/Unit/ValueObjects/MoneyTest.php`, covering factories, arithmetic, comparisons, immutability, currency mismatch errors, and real-world double-entry balance check use cases.

### Fixed broken factories

The following factories had bugs that would cause test failures in any environment that actually runs them:

| Factory | Bug | Fix |
|---------|-----|-----|
| `AttendanceTutorFactory` | `pending_rate` set to a random integer (50000-200000) instead of boolean | Default to `false`; added `pendingRate()` and `paid()` state helpers |
| `AttendanceFactory` | Used invalid enum values `open`/`closed` for `status` (schema allows `scheduled`/`ongoing`/`finished`/`skipped`/`postponed`) | Use valid enum values |
| `AttendanceFactory` | Set `marked_by` to null despite the column being NOT NULL | Auto-create a User via factory |
| `PayrollRunFactory` | Default status was `draft`, but `PayrollService` only checks for `approved` — the factory default was misleading | Default to `pending` (matches service expectation); added `approved()` / `reversed()` helpers |
| `TutorAvailabilityFactory` | Used invalid enum value `unavailable` (schema allows `available`/`not_available`/`occupied`) | Default to `available`; added `occupied()` / `notAvailable()` helpers |
| `TutorFactory` | Missing `withUser()` and `withRate()` methods that existing tests call | Added both, plus `forUser()` for attaching an existing user |
| `UserFactory` | Default `role` was missing (column has NOT NULL default in migration) | Default to `admin`; added `role()`, `admin()`, `cfo()`, `tutor()`, `student()` helpers |
| `JournalFactory` | Missing `type` column (added by migration `2026_05_16_094429`) | Default to `general`; improved `withItems()` helper to be defensive about missing accounts |

### Critical-path unit tests

**`tests/Unit/Services/PayrollServiceTest.php`** (12 tests)

Covers the full payroll lifecycle:
- `createPayrollRun`: month normalization, idempotency guard, allows new run after reversal
- `approvePayrollRun`: skips tutors with no unpaid attendance, skips pending_rate attendances, blocks double-approval, verifies correct debit/credit account codes in the journal
- `reversePayrollRun`: blocks reversal of non-approved runs, creates reversal journal, unmarks `paid_at`, idempotent for already-reversed journals

**`tests/Unit/Services/EnrollmentServiceTest.php`** (extended, +5 tests)

Existing tests had a wrong assertion (`payment_status` for `full upfront` should be `FULL`, not `PENDING`). Added edge cases:
- Reuses existing student when `existing_student_id` provided (no orphan user created)
- Marks `TutorAvailability` as `occupied` when enrolling with a schedule that matches
- Creates journal with `BANK` account (not `CASH`) when `payment_channel` is `bank`
- Throws `ModelNotFoundException` when class session doesn't belong to the program
- Attaches multiple tutors to a class session for private programs

### Critical-path feature tests

**`tests/Feature/Admin/ScheduleControllerTest.php`** (8 tests)
- Authorization (guest redirected, non-admin forbidden)
- Create schedule happy path
- Conflict detection (slot already occupied, class session already scheduled in that slot)
- Validation (required fields)
- Update (move schedule to different slot)
- Destroy

**`tests/Feature/Admin/StudentControllerTest.php`** (10 tests)
- Authorization
- Index and data endpoint (paginated JSON response structure)
- Filter by `inactive` and `overdue`
- Show student detail
- Update profile and reset password
- Email uniqueness validation
- Destroy with soft invariants (cannot delete student with active enrollment or journal history)

---

## Layer 2: Resilience & Reliability

### Idempotency middleware (`app/Http/Middleware/IdempotencyMiddleware.php`)

RFC-draft `Idempotency-Key` header support for unsafe HTTP methods. Guarantees that retrying a request (network blip, double-click) produces identical side effects.

**Behavior:**
- Reads `Idempotency-Key` header; if absent, request passes through (opt-in)
- Uses cache lock to prevent thundering herd on simultaneous identical requests
- Caches successful (2xx) and client-error (4xx) responses
- Does NOT cache 5xx responses (client should retry)
- Replay responses are marked with `X-Idempotent-Replay: true` header
- Cache key includes route name to prevent cross-endpoint key reuse
- TTL configurable per-route (default 300 seconds)
- Uses Laravel's cache lock with 5-second blocking; returns 409 if lock can't be acquired

**Test coverage:** 6 tests in `tests/Feature/Middleware/IdempotencyMiddlewareTest.php`

**Usage in routes:**
```php
Route::post('/finance/journals', [JournalController::class, 'store'])
    ->middleware('idempotent:journals.store,300');
```

### Queue infrastructure for async imports

**`app/Jobs/AbstractImportJob.php`** — Base class for async CSV/Excel imports.

Why async imports matter:
- A 10,000-row CSV import takes 30+ seconds synchronously
- HTTP request timeouts force the client to retry, causing duplicate processing
- Load balancer retries multiply the problem
- Pushing to a queue frees the HTTP worker, lets the user continue, allows retry with backoff

**Features:**
- Exponential backoff: 10s → 30s → 90s (3 attempts)
- 300-second job timeout
- Per-job `import_id` for tracing
- Structured logging at every stage (`import.started`, `import.completed`, `import.failed`, `import.dlq`)
- File cleanup on success; preserved on failure for manual inspection
- Routes to a dedicated `imports` queue so heavy imports don't block email/notification jobs
- `failed()` hook logs to `critical` level for alerting

**`app/Jobs/ImportClassroomsJob.php`** — Concrete implementation for classroom CSV imports.

Idempotent (uses `updateOrCreate` by name), per-row error collection, continues processing remaining rows on row-level failures.

### Dead Letter Queue (DLQ) configuration

`config/queue.php` updated with:
- `failed_jobs` table uses `database-uuids` driver — each failed job gets a UUID for `php artisan queue:retry {uuid}`
- Production-grade defaults documented inline
- `failover` connection falls back from `database` to `deferred` (in-process) if the DB is down

### Structured JSON logging

`config/logging.php` updated:
- `daily` channel now outputs JSON with `JsonFormatter`
- Includes `WebProcessor` (adds `ip`, `url`, `referrer`, `user_agent` to every log)
- Includes `IntrospectionProcessor` for `warning+` logs (adds class/function/line)
- 30-day retention (configurable via `LOG_DAILY_DAYS`)
- `stderr` channel for containerized deployments (Docker, Kubernetes) — outputs JSON to stdout
- `LOG_STACK=daily,stderr` recommended for production

### Graceful shutdown & health checks

**`app/Console/Commands/HealthCheckCommand.php`** — Load balancer probe command.

Checks:
1. Database connectivity (`SELECT 1`)
2. Redis connectivity (skipped if not configured)
3. Storage directory writability (writes a probe file)

Exit code 0 = healthy, 1 = unhealthy. Designed to back the `/up` route or a dedicated `/healthz` endpoint.

### Exception handling

`bootstrap/app.php` updated with consistent error rendering:
- `DomainException` → 422 with `{error: "domain_violation", message: ...}` for JSON requests
- `ModelNotFoundException` → 404 with `{error: "resource_not_found", ...}` for JSON requests
- `ThrottleRequestsException` → 429 with `Retry-After: 60` header
- `TokenMismatchException` → redirect to login (existing behavior preserved)

### Trust proxies

`bootstrap/app.php` now calls `$middleware->trustProxies(at: '*')` — required when behind a load balancer for:
- Correct `HTTPS` detection (so `URL::forceScheme('https')` works)
- Real client IP in logs (not the LB's IP)

In production with a known LB IP range, replace `'*'` with the specific range.

---

## Layer 5: Scalability & Performance

### Composite indexes migration

`database/migrations/2026_06_23_000001_add_composite_indexes_for_production.php`

Adds 13 composite indexes covering the most frequent query patterns:

| Table | Index | Use case |
|-------|-------|----------|
| `schedules` | `(classroom_id, day, time_block)` | Room occupancy check during enrollment |
| `schedules` | `(class_session_id, day, time_block)` | Class session schedule lookup |
| `attendance` | `(date, time_block, class_session_id)` | Duplicate attendance detection |
| `attendance` | `(class_session_id, date)` | Session history lookup |
| `attendance_tutor` | `(tutor_id, paid_at, pending_rate)` | PayrollService query (index-only scan) |
| `attendance_tutor` | `(attendance_id, tutor_id)` | Attendance record lookup |
| `enrollments` | `(student_id, status)` | Student enrollment history |
| `enrollments` | `(class_session_id, status)` | Active/waitlist count for quota check |
| `enrollments` | `(status, payment_status)` | Status dashboard filters |
| `installments` | `(enrollment_id, paid_at)` | Unpaid installment count |
| `installments` | `(paid_at, due_date)` | Overdue installment reminder job |
| `journals` | `(date, type)` | Financial reports by date range + type |
| `journal_items` | `(account_id, journal_id)` | General ledger / trial balance |
| `tutor_availability` | `(status, tutor_id)` | Tutor occupancy stats |
| `room_bookings` | `(classroom_id, date, time_block)` | Room conflict detection |
| `users` | `(role, email)` | User listing by role |

**Migration is reversible** — `down()` method drops all added indexes. Safe to run on a live MySQL 8+ database (InnoDB online DDL).

### Pagination already in place

Audited all `data()` endpoints — they already use either `->paginate()` or manual `skip/take` with `last_page` in the JSON response. No changes needed.

---

## Foundation: Configuration & Operability

### `.env.example` (production-ready template)

- MySQL as default DB (was SQLite)
- Database/Redis defaults documented for cache, session, queue
- `MAIL_FROM_ADDRESS` changed from `hello@example.com` to `noreply@justspeak.example`
- Added rate limiting config (`LOGIN_RATE_LIMIT`, `API_RATE_LIMIT`)
- Added log retention config (`LOG_DAILY_DAYS=30`)
- Added `LOG_STACKTRACES` toggle (false in prod, true in dev)
- Inline documentation for every setting

### Queue worker startup (documented)

```bash
php artisan queue:work --queue=default,imports --tries=3 --backoff=10,30,90 --timeout=300
```

Use a process manager (Supervisor, systemd, or Docker `restart: always`) to keep the worker alive.

---

## What was NOT done (deliberately out of scope)

Per user direction, the following were skipped:
- **Layer 3 (Security):** Rate limiting middleware, security headers (CSP/HSTS), session encryption — app stays Blade monolith, no JWT/API surface
- **Layer 4 (Observability):** CI/CD pipeline (GitHub Actions), distributed tracing, alerting hooks
- **Hardcoded password fix:** `bcrypt('password123')` in `EnrollmentService` left as-is per user's explicit "leave it" decision
- **Repository pattern / Clean Architecture refactor:** App is a working Blade monolith; refactor would break too much
- **API versioning / OpenAPI spec:** No API surface to version (Blade app)
- **Caching strategy:** No high-read endpoints identified that would benefit from cache-aside

---

## How to verify the changes

```bash
# 1. Install dependencies
composer install

# 2. Run migrations (including the new composite indexes)
php artisan migrate

# 3. Run the test suite
php artisan test

# 4. Run the health check
php artisan app:health-check --detailed

# 5. Start a queue worker (for async imports)
php artisan queue:work --queue=default,imports --tries=3 --backoff=10,30,90
```

## Files added / modified

**Added (15 files):**
- `app/ValueObjects/Money.php`
- `app/Http/Middleware/IdempotencyMiddleware.php`
- `app/Jobs/AbstractImportJob.php`
- `app/Jobs/ImportClassroomsJob.php`
- `app/Console/Commands/HealthCheckCommand.php`
- `database/migrations/2026_06_23_000001_add_composite_indexes_for_production.php`
- `tests/Unit/ValueObjects/MoneyTest.php`
- `tests/Unit/Services/PayrollServiceTest.php`
- `tests/Feature/Admin/ScheduleControllerTest.php`
- `tests/Feature/Admin/StudentControllerTest.php`
- `tests/Feature/Middleware/IdempotencyMiddlewareTest.php`

**Modified (10 files):**
- `bootstrap/app.php` — exception handling, trust proxies, idempotency alias
- `config/logging.php` — structured JSON logging
- `config/queue.php` — DLQ documentation
- `.env.example` — MySQL defaults, production-ready template
- `database/factories/UserFactory.php` — role helpers
- `database/factories/TutorFactory.php` — `withUser()`, `withRate()`, `forUser()`
- `database/factories/AttendanceFactory.php` — fixed enum values, marked_by
- `database/factories/AttendanceTutorFactory.php` — fixed pending_rate type
- `database/factories/PayrollRunFactory.php` — fixed default status, added helpers
- `database/factories/TutorAvailabilityFactory.php` — fixed enum values
- `database/factories/JournalFactory.php` — added type column, defensive withItems()
- `tests/Unit/Services/EnrollmentServiceTest.php` — fixed wrong assertion, added edge cases
