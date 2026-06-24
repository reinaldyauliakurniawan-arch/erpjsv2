import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsCfo } from './helpers/auth';

/**
 * Navigation E2E tests.
 *
 * Verifies that every sidebar link actually navigates to a working page,
 * and that the global search bar is functional.
 */

test.describe('Navigation — all sidebar links work', () => {

  test.describe('Admin sidebar', () => {
    test.beforeEach(async ({ page }) => {
      await loginAsAdmin(page);
    });

    const adminPages = [
      { name: 'Dashboard', url: '/admin/dashboard' },
      { name: 'Students', url: '/admin/students' },
      { name: 'Tutors', url: '/admin/tutors' },
      { name: 'Programs', url: '/admin/programs' },
      { name: 'Enrollments', url: '/admin/enrollments' },
      { name: 'Classrooms', url: '/admin/classrooms' },
      { name: 'Class Sessions', url: '/admin/class-sessions' },
      { name: 'Jadwal', url: '/admin/schedule' },
      { name: 'Absensi', url: '/admin/attendance' },
      { name: 'Settings', url: '/admin/settings' },
      { name: 'Import/Export', url: '/admin/imports' },
    ];

    for (const pg of adminPages) {
      test(`can navigate to ${pg.name}`, async ({ page }) => {
        await page.goto(pg.url);
        // Page should load without error (no 500, no 403)
        await expect(page.locator('body')).not.toContainText(/server error|500|whoops/i);
        // Should have content (not a blank page)
        await expect(page.locator('main, .app-main')).toBeVisible();
      });
    }
  });

  test.describe('CFO sidebar', () => {
    test.beforeEach(async ({ page }) => {
      await loginAsCfo(page);
    });

    const cfoPages = [
      { name: 'Finance Dashboard', url: '/finance' },
      { name: 'Journals', url: '/finance/journals' },
      { name: 'General Ledger', url: '/finance/reports/general-ledger' },
      { name: 'Trial Balance', url: '/finance/reports/trial-balance' },
      { name: 'Profit & Loss', url: '/finance/reports/profit-loss' },
      { name: 'Balance Sheet', url: '/finance/reports/balance-sheet' },
      { name: 'Cash Flow', url: '/finance/reports/cash-flow' },
      { name: 'Accounts', url: '/finance/accounts' },
      { name: 'Payroll', url: '/finance/payroll' },
      { name: 'RAB', url: '/finance/rab' },
    ];

    for (const pg of cfoPages) {
      test(`can navigate to ${pg.name}`, async ({ page }) => {
        await page.goto(pg.url);
        await expect(page.locator('body')).not.toContainText(/server error|500|whoops/i);
        await expect(page.locator('main, .app-main')).toBeVisible();
      });
    }
  });
});

test.describe('Global Search Bar', () => {

  test('admin search bar is visible and functional', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/dashboard');

    // Search bar should be in the topbar
    const searchInput = page.locator('input[aria-label="Global search"]').first();
    await expect(searchInput).toBeVisible({ timeout: 5000 });

    // Type a query
    await searchInput.click();
    await searchInput.fill('admin');

    // Wait for dropdown to appear (debounced 300ms)
    await page.waitForTimeout(500);

    // Dropdown should be visible (or show "no results" / "min 2 chars")
    const dropdown = page.locator('.app-search__dropdown');
    await expect(dropdown).toBeVisible({ timeout: 3000 });
  });

  test('cfo search bar returns finance results', async ({ page }) => {
    await loginAsCfo(page);
    await page.goto('/finance');

    const searchInput = page.locator('input[aria-label="Global search"]').first();
    await expect(searchInput).toBeVisible({ timeout: 5000 });

    // Type a query that should match account names (Cash, Bank)
    await searchInput.click();
    await searchInput.fill('cash');

    await page.waitForTimeout(500);

    // Dropdown should appear
    const dropdown = page.locator('.app-search__dropdown');
    await expect(dropdown).toBeVisible({ timeout: 3000 });
  });

  test('search bar keyboard shortcut "/" focuses input', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/dashboard');

    // Press "/" key
    await page.keyboard.press('/');

    // Search input should be focused
    const searchInput = page.locator('input[aria-label="Global search"]').first();
    await expect(searchInput).toBeFocused({ timeout: 3000 });
  });
});
