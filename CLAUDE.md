# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Web ERP for **Just Speak**, a language-course institute. It covers the full business
cycle: student enrollment, class/session scheduling, attendance, installment payments,
double-entry accounting, financial reports, tutor payroll, fixed assets, and budgeting (RAB).

Stack: Laravel 13 (PHP 8.3+), Blade + Tailwind CSS 4 + DaisyUI 5 + Alpine.js, Vite 8,
SQLite by default (MySQL/Postgres supported). Tabulator for data grids, Chart.js for charts.
Auth is Laravel Breeze.

**Language convention:** code comments and all user-facing strings are in Indonesian.
Match that when editing — don't convert existing Indonesian text to English.

## Commands

```bash
composer dev          # run everything: php artisan serve + queue:listen + pail (logs) + vite
composer setup        # first-time: install, .env, key, migrate, seed, npm install, build
composer test         # clears config cache, then php artisan test  (SQLite :memory:)
php artisan test tests/Feature/SearchControllerTest.php          # single file
php artisan test --filter=test_method_name                       # single test
npm run build         # production frontend assets
npm run e2e           # Playwright E2E — requires app running & seeded on :8000 (see below)
vendor/bin/pint       # code style (Laravel Pint)
```

Seeded login credentials (from `InitialDataSeeder`): `admin@justspeak.test`,
`cfo@justspeak.test`, `tutor1@justspeak.test` — all password `password`.

E2E tests run sequentially against a shared, seeded database on `http://localhost:8000`;
start `php artisan serve` and `php artisan db:seed --force` first. They mutate real data.

The scheduler (`routes/console.php`) and queue worker must both be running for expiration
checks, payment reminders, and monthly adjusting journals — `composer dev` starts both.

## Roles & authorization

**Four** distinct roles on `users.role`: `admin` (`/admin/*`), `cfo` (`/finance/*`),
`tutor` (`/tutor/*`), `student` (`/student/*`). `cfo` is **not** a flavour of admin — it
owns the finance area and admin cannot reach `/finance/*` (and vice versa). Routes are
grouped by prefix in `routes/web.php` and gated by the `role` middleware alias
(`->middleware('role:admin')`, `role:cfo`, …), which returns JSON 401/403 for XHR and
redirects/aborts for web.

- Canonical list: `App\Enums\Role` (`ADMIN`, `CFO`, `TUTOR`, `STUDENT`). Compare roles via
  `User` helpers — `$user->isAdmin()`, `isCfo()`, `isTutor()`, `isStudent()`,
  `roleEnum()`, and `isBackOffice()` (admin **or** cfo — the only place the two are
  deliberately merged, e.g. the topbar global search). Don't write bare `$user->role === '…'`.
- `role` is deliberately **not** in `User::$fillable` — set it explicitly
  (`$user->role = Role::TUTOR->value`) to avoid mass-assignment privilege escalation.
- `AppServiceProvider` registers `Gate::before` that auto-passes any ability for `admin`/`cfo`
  (`isBackOffice()`). This is only a shortcut for `authorize()` calls behind route middleware —
  the real gate is the route-level `role` middleware, not this.
  There are **no Policy classes**; `$this->authorize()` calls in controllers rely on this plus
  the route-level `role` middleware. Don't add Policies expecting them to be discovered.

## Scheduling: day / time_block format

`schedules.day`, `schedules.time_block`, `tutor_availability.day/time_block` are free-string
columns fed from many sources (forms, spreadsheet imports, enum dropdowns). The **canonical
form** — enforced on every write and used for cross-table matching (tutor occupancy, the
"available tutors" dropdown, `TutorAssignmentService::recomputeAvailability`) — is:

- `day`: Indonesian, capitalised — `Senin`..`Minggu` (`App\Enums\DayOfWeek` values;
  `DayOfWeek::fromDate($date)` for a weekday from a date, **not** `Carbon::format('l')`).
- `time_block`: colon separator, no spaces — `09:00-10:30` (`App\Enums\TimeBlock` values).

Always run inbound values through **`App\Support\ScheduleFormat`** (`day()`, `timeBlock()`,
`slotKey()`, plus `DAYS` / `TIME_BLOCKS` constants) before writing or comparing. A tutor's
`tutor_availability.status` of `occupied` is derived state — recomputed from actual class
assignments, never set by hand.

**Classroom kind** (`classrooms.kind`, `App\Enums\ClassroomKind`): `physical` (a real room
at the JS office), `online` (class runs anywhere), `offsite` (B2B — tutor sent to a client
site). Only `physical` counts toward room-occupancy stats and is subject to capacity / room
double-booking checks — use `$classroom->countsForOccupancy()` / `Classroom::physical()`,
not the legacy `is_at_just_speak` boolean (which is now derived from `kind` via
`Classroom::saving()` and kept only for the import/export CSV). `online` and `offsite`
classes still appear in every schedule / class-session view — they are just excluded from
the occupancy math.

## Accounting core

All money movement flows through **`App\Services`**, never controllers directly. Services
compose each other and each wraps its work in `DB::transaction` with `lockForUpdate` on the
contended rows.

- **`AccountingService::createJournal`** is the only way to write to the ledger. It validates
  total debit == total credit (throws `BalanceMismatchException`) and is idempotent on the
  `reference` string (throws `IdempotencyException` if it already exists — checked under a
  row lock inside the transaction).
- **`EnrollmentService`** — enrollment, waitlisting, room-occupancy checks, installment
  generation. Emits journals for tuition receivable / deferred revenue.
- **`AttendanceService::markAttendance`** — recording attendance is an accounting event: it
  triggers `RevenueRecognitionService` and posts the tutor-fee journal.
- **`RevenueRecognitionService`** — deferred-revenue / accounts-receivable sub-ledger per
  enrollment. It **recomputes** everything from `installments` and `attendance_student` each
  call; it stores no separate state and takes no locks itself — **callers must
  `lockForUpdate` the `Enrollment` row inside their own transaction first** (documented at
  length in the class).
- **`PayrollService`** — payroll runs, approve, reverse; each transition posts journals.

Chart of accounts: `App\Enums\AccountCode` (well-known codes) + `ChartOfAccountsSeeder`.
Adjusting journals have their own models/controller and a monthly scheduled generator.

## Budgeting (RAB) — CFO only

All under `/finance/*` (`role:cfo`). `rabs` holds the annual budget per expense
account (quarterly split, `total` = `q1+q2+q3+q4` stored column, plus `rab_prev`).
Three pages: **RAB** (`RabController`, edit the budget), **Realisasi RAB**
(`RabRealisasiController`, quarterly budget-vs-actual derived from `journal_items`),
and **Tracker RAB** (`RabTrackerController`, monthly budget-vs-actual from
`rab_monthly_actuals` — a CFO-maintained manual store for the cash→accrual
transition, with a "sync from journals" action and a manual `__REVENUE__` row for
the monthly P/L). The CFO's real spreadsheet lives at
`_migrasi/sumber/Dashboard FInance & Admin.xlsx` (gitignored); its data was loaded
via `_migrasi/import_rab_tracker.php` (also gitignored — never commit `_migrasi/`).

## Idempotency for money-mutating endpoints

The `idempotent` middleware alias (`IdempotencyMiddleware`) is applied to routes that move
money (installment paid, journal reverse, payroll store/approve/reverse). It's **opt-in per
request** via an `Idempotency-Key` header; without the header the request passes through.
Cache keys are namespaced by user id and route name. Uses the default `CACHE_STORE`
(`database` by default) — use Redis/DB, not `array`, for multi-instance deployments.

## Error handling

`bootstrap/app.php` maps exceptions to JSON for XHR requests:
`App\Exceptions\DomainException` → 422 (business-rule violations — throw this from services),
`ModelNotFoundException` → 404, `ThrottleRequestsException` → 429,
`TokenMismatchException` → redirect to login. Other accounting exceptions
(`BalanceMismatchException`, `AccountNotFoundException`, `IdempotencyException`) extend the
base PHP `Exception`.

## Not application code

`agent-skills/` and `.jcode/` are vendored tooling for other AI agents (slash-command
definitions, plugin manifests) — they have their own git repo and are unrelated to the ERP.
`.aider*` files are Aider history. Ignore all of these when reasoning about the app.
