# E2E Tests with Playwright

E2E tests that verify every CRUD button actually works — not just that
the page loads, but that clicking "Create" opens a modal, filling the form
and submitting creates a record, and clicking "Delete" removes it.

## Setup

```bash
# 1. Install dependencies
npm install

# 2. Install Playwright browser
npx playwright install chromium

# 3. Start the Laravel app (in a separate terminal)
php artisan serve

# 4. Seed the database (creates test users + sample data)
php artisan db:seed --force
```

## Run Tests

```bash
# Run all E2E tests
npm run e2e

# Run with interactive UI (shows browser, step through tests)
npm run e2e:ui

# Run a specific test file
npx playwright test tests/e2e/auth.spec.js

# Run tests matching a pattern
npx playwright test --grep "login"

# View HTML report after run
npm run e2e:report
```

## Test Credentials

These are created by `InitialDataSeeder` (runs automatically during `db:seed`):

| Role   | Email                    | Password   |
|--------|--------------------------|------------|
| Admin  | admin@justspeak.test     | password   |
| CFO    | cfo@justspeak.test       | password   |
| Tutor  | tutor1@justspeak.test    | password   |

## Test Structure

```
tests/e2e/
├── helpers/
│   └── auth.js                              # Login/logout helpers for each role
├── auth.spec.js                             # Login, logout, role redirect, forbidden access
├── admin-students.spec.js                   # Student list, detail, update
├── admin-tutors.spec.js                     # Tutor list, detail, create
├── admin-classrooms.spec.js                 # Classroom list, create, edit/delete
├── admin-programs.spec.js                   # Program list, create, edit
├── admin-settings.spec.js                   # User CRUD, color settings
├── admin-class-sessions.spec.js             # Class session CRUD, assign/remove, schedules
├── admin-enrollments.spec.js                # Enrollment list, create form, detail, expire/graduate
├── admin-schedule-attendance-tracker.spec.js # Schedule grid, attendance, tracker
├── admin-imports-dashboard.spec.js          # Import/export, admin dashboard
├── cfo-accounts.spec.js                     # Account list, create
├── cfo-journals.spec.js                     # Journal list, create balanced entry
├── cfo-payroll.spec.js                      # Payroll list, create, approve, reverse
├── cfo-reports-assets-rab.spec.js           # All 8 reports, adjusting journals, fixed assets, RAB,
│                                            # finance dashboard, journal detail, all exports
├── navigation.spec.js                       # All sidebar links + search bar
├── button-smoke.spec.js                     # No JS errors, no dead clicks, modals open
├── tutor-all.spec.js                        # Tutor dashboard, attendance, schedule, availability,
│                                            # practice CRUD, tracker, authz check
├── student-all.spec.js                      # Student dashboard, practice, tracker, access control
├── profile-auth.spec.js                     # Profile edit, password change, forgot password,
│                                            # confirm password
└── edge-cases.spec.js                       # Apostrophe names, XSS, form validation, modal close,
                                             # empty states, double-submit protection, pagination
```

**Total: 20 test files, 120+ test cases**

## What Each Test Verifies

### auth.spec.js
- Admin/CFO/Tutor can login → redirected to correct dashboard
- Wrong password → stays on login page
- Logout → redirected to login
- Unauthenticated → redirected to login
- Admin cannot access CFO routes (403)
- Tutor cannot access admin routes (403)

### admin-students.spec.js
- Student list page loads with table
- Student detail page loads
- `/admin/students/data` returns JSON
- Can update student profile

### admin-classrooms.spec.js
- Classroom list loads
- Create button opens modal → fill form → submit → redirect to list
- Edit/Delete buttons are present and not disabled

### admin-tutors.spec.js
- Tutor list loads
- Tutor detail page loads
- Create button opens modal → fill form → submit → redirect

### admin-programs.spec.js
- Program list loads
- Create button opens form → fill → submit → redirect
- Edit buttons are clickable

### admin-settings.spec.js
- Settings page loads with user table
- Create user modal opens with all fields
- Can create a new admin user
- Edit button opens modal with pre-filled data
- Delete button opens confirmation dialog
- Color settings page loads

### cfo-accounts.spec.js
- Account list loads
- Create button opens modal → fill → submit → redirect

### cfo-journals.spec.js
- Journal list loads
- Create form loads with date/reference/description fields
- `/finance/journals/data` returns JSON
- Can create a balanced journal entry (debit = credit)

### cfo-payroll.spec.js
- Payroll list loads
- Create payroll run modal opens
- Pending runs have clickable Approve button
- Approved runs have clickable Reverse button

### navigation.spec.js
- Every admin sidebar link loads without 500/403
- Every CFO sidebar link loads without 500/403
- Search bar is visible for admin/CFO
- Search bar returns results when typing
- "/" keyboard shortcut focuses search input

### button-smoke.spec.js
- No JavaScript console errors on admin dashboard
- All buttons on students page are clickable (not disabled)
- Classrooms create button opens modal
- Settings edit button opens modal with data
- Finance month filter triggers page reload without crash
- Search dropdown renders content
- Sidebar links navigate to correct URL
- Profile page loads with editable fields

## CI Integration

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_sqlite, mbstring, xml, curl, zip
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: composer install --no-interaction
      - run: npm install
      - run: npm run build
      - run: npx playwright install chromium
      - run: php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan db:seed --force
      - run: php artisan serve --port=8000 &
      - run: npx playwright test
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: playwright-report/
```

## Debugging Failed Tests

```bash
# Run with browser visible (headed mode)
npx playwright test --headed

# Run with Playwright Inspector (step through)
npx playwright test --debug

# Run single test with full trace
npx playwright test --grep "login" --trace on

# View trace after failure
npx playwright show-trace test-results/trace.zip
```
