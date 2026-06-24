# Just Speak — Test Suite

## Struktur File

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── AccountingServiceTest.php   ← logika journal, balance, idempotency
│   │   ├── EnrollmentServiceTest.php   ← logika enrollment, waitlist, room occupancy
│   │   └── PayrollServiceTest.php      ← payroll run, approve, reverse, edge cases
│   └── ValueObjects/
│       └── MoneyTest.php               ← Money value object (arithmetic, parsing, formatting)
└── Feature/
    ├── Auth/
    │   └── AuthenticationTest.php      ← login, logout, role redirect, rate limit
    ├── Admin/
    │   ├── EnrollmentControllerTest.php ← CRUD, installment paid, expire, graduate
    │   ├── JournalControllerTest.php    ← manual journal, reverse
    │   ├── PayrollControllerTest.php    ← run payroll, approve
    │   ├── ScheduleControllerTest.php   ← CRUD, conflict detection, role guard
    │   └── StudentControllerTest.php    ← CRUD, filter, delete guard, role guard
    ├── Middleware/
    │   └── IdempotencyMiddlewareTest.php ← RFC Idempotency-Key header behavior
    ├── AttendanceControllerTest.php     ← admin & tutor attendance, revenue recognition
    └── SearchControllerTest.php         ← global search (admin & CFO role isolation)
```

---

## Setup

Test suite sudah dikonfigurasi untuk SQLite in-memory — **tidak perlu MySQL server**.

`phpunit.xml` sudah set:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Jadi `php artisan test` langsung jalan di fresh clone tanpa konfigurasi database eksternal.

---

## Cara Jalankan

### Semua test sekaligus
```bash
php artisan test
```

### Dengan output detail
```bash
php artisan test --verbose
```

### Satu file saja
```bash
php artisan test tests/Unit/Services/AccountingServiceTest.php
php artisan test tests/Feature/SearchControllerTest.php
```

### Satu test method saja
```bash
php artisan test --filter "it_creates_a_balanced_journal_entry"
```

### Hanya Unit Tests
```bash
php artisan test tests/Unit
```

### Hanya Feature Tests
```bash
php artisan test tests/Feature
```

---

## Coverage Report
```bash
php artisan test --coverage
php artisan test --coverage --min=80
```

---

## Yang Ditest

| Area | Test | Kasus |
|---|---|---|
| AccountingService | Unit | Balance validation, idempotency, account not found, rollback |
| EnrollmentService | Unit | Private/group enroll, waitlist, quota, room occupancy, installment, journal |
| PayrollService | Unit | create/approve/reverse lifecycle, pending_rate skip, idempotent reversal |
| Money | Unit | Factories, arithmetic, comparisons, parsing (ID + EN formats), formatting |
| EnrollmentController | Feature | CRUD, mark paid, expire, graduate, role guard |
| JournalController | Feature | Create, reverse, duplicate prevention |
| PayrollController | Feature | Run, approve, double-approve guard |
| ScheduleController | Feature | CRUD, slot conflict detection, validation, role guard |
| StudentController | Feature | CRUD, filter (inactive/overdue), delete guards, role guard |
| SearchController | Feature | Role isolation (admin vs CFO), substring match, min 2 chars, auth |
| IdempotencyMiddleware | Feature | Same-key replay, 5xx not cached, 4xx cached, GET bypass |
| AttendanceController | Feature | Admin delete, tutor absen, revenue recognition, duplicate prevention |
| Authentication | Feature | Login per role, wrong password, rate limit, logout, role guard |

**Total: ~90 test cases**

