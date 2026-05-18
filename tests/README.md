# Just Speak — Test Suite

## Struktur File

```
tests/
├── Unit/
│   └── Services/
│       ├── AccountingServiceTest.php   ← logika journal, balance, idempotency
│       └── EnrollmentServiceTest.php   ← logika enrollment, waitlist, room occupancy
└── Feature/
    ├── Auth/
    │   └── AuthenticationTest.php      ← login, logout, role redirect, rate limit
    ├── Admin/
    │   ├── EnrollmentControllerTest.php ← CRUD, installment paid, expire, graduate
    │   ├── JournalControllerTest.php    ← manual journal, reverse
    │   └── PayrollControllerTest.php    ← run payroll, approve
    └── AttendanceControllerTest.php     ← admin & tutor attendance, revenue recognition
```

---

## Setup

### 1. Buat database testing
Di `phpunit.xml` atau `.env.testing`, pastikan pakai SQLite in-memory:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Atau buat database MySQL terpisah:
```env
# .env.testing
DB_DATABASE=just_speak_test
```

### 2. Pastikan factories sudah ada
Test ini butuh factories untuk semua model. Buat jika belum ada:
```bash
php artisan make:factory AccountFactory --model=Account
php artisan make:factory EnrollmentFactory --model=Enrollment
php artisan make:factory InstallmentFactory --model=Installment
php artisan make:factory JournalFactory --model=Journal
php artisan make:factory PayrollRunFactory --model=PayrollRun
php artisan make:factory AttendanceFactory --model=Attendance
php artisan make:factory ScheduleFactory --model=Schedule
php artisan make:factory TutorFactory --model=Tutor
php artisan make:factory ClassSessionFactory --model=ClassSession
```

Beberapa factory membutuhkan **states** khusus:
```php
// TutorFactory — state withUser()
public function withUser(array $attrs = []): static
{
    return $this->state(fn () => [
        'user_id' => User::factory()->create(array_merge(['role' => 'tutor'], $attrs))->id,
    ]);
}

// EnrollmentFactory — state withRelations()
public function withRelations(): static
{
    return $this->state(fn () => [
        'student_id'       => Student::factory()->create()->id,
        'program_id'       => Program::factory()->create()->id,
        'class_session_id' => ClassSession::factory()->create()->id,
    ]);
}

// JournalFactory — state withItems()
public function withItems(): static
{
    return $this->afterCreating(function (Journal $journal) {
        JournalItem::factory()->count(2)->create(['journal_id' => $journal->id]);
    });
}
```

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
php artisan test tests/Unit/Services/EnrollmentServiceTest.php
php artisan test tests/Feature/Admin/EnrollmentControllerTest.php
php artisan test tests/Feature/Auth/AuthenticationTest.php
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

### Paralel (lebih cepat)
```bash
php artisan test --parallel
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
| EnrollmentController | Feature | CRUD, mark paid, expire, graduate, role guard |
| JournalController | Feature | Create, reverse, duplicate prevention |
| PayrollController | Feature | Run, approve, double-approve guard |
| AttendanceController | Feature | Admin delete, tutor absen, revenue recognition, duplicate prevention |
| Authentication | Feature | Login per role, wrong password, rate limit, logout, role guard |

**Total: ~50 test cases**
