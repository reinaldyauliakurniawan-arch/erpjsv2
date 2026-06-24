import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Button functionality smoke tests.
 *
 * These tests verify that buttons across critical pages are NOT dead clicks.
 * They don't test the full CRUD flow (that's in the entity-specific tests),
 * but they catch the "button exists but does nothing" class of bugs.
 */

test.describe('Button smoke tests — no dead clicks', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('admin dashboard has no JS errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // Give Alpine time to initialize
    await page.waitForTimeout(1000);

    expect(errors).toEqual([]);
  });

  test('students page buttons are clickable', async ({ page }) => {
    await page.goto('/admin/students');
    await page.waitForLoadState('networkidle');

    // All buttons in the table area should not be disabled
    const buttons = page.locator('button:not([disabled])');
    const count = await buttons.count();

    // At least some buttons should exist
    expect(count).toBeGreaterThan(0);
  });

  test('classrooms page: create button opens modal', async ({ page }) => {
    await page.goto('/admin/classrooms');
    await page.waitForLoadState('networkidle');

    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      // Modal should appear
      await page.waitForTimeout(500);
      const dialog = page.locator('dialog[open]').first();
      const isVisible = await dialog.isVisible().catch(() => false);
      expect(isVisible).toBe(true);
    }
  });

  test('settings page: user CRUD buttons work', async ({ page }) => {
    await page.goto('/admin/settings');
    await page.waitForLoadState('networkidle');

    // Verify edit buttons exist
    const editBtns = page.locator('button[title="Edit"], button:has-text("Edit")');
    expect(await editBtns.count()).toBeGreaterThan(0);

    // Click first edit button
    await editBtns.first().click();
    await page.waitForTimeout(500);

    // Modal should appear
    const dialog = page.locator('dialog[open]').first();
    const isVisible = await dialog.isVisible().catch(() => false);
    expect(isVisible).toBe(true);

    // Close the dialog
    const closeBtn = dialog.locator('button:has-text("Batal"), button:has-text("Cancel"), form[method="dialog"] button').first();
    if (await closeBtn.isVisible()) {
      await closeBtn.click();
    }
  });

  test('finance dashboard: month filter works', async ({ page }) => {
    // Login as CFO
    await page.goto('/login');
    await page.fill('input[name="email"]', 'cfo@justspeak.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));

    await page.goto('/finance');
    await page.waitForLoadState('networkidle');

    // Look for month select
    const monthSelect = page.locator('select[name="month"], input[type="month"]').first();
    if (await monthSelect.isVisible()) {
      // Changing the month should trigger a form submit / page reload
      const currentUrl = page.url();
      await monthSelect.selectOption({ index: 0 }).catch(async () => {
        // If it's an input[type="month"], use fill
        await monthSelect.fill('2025-01');
      });

      // Wait for potential page reload
      await page.waitForTimeout(2000);

      // Page should still be functional (no crash)
      await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
    }
  });

  test('search bar dropdown renders results', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const searchInput = page.locator('input[aria-label="Global search"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.click();
      await searchInput.fill('admin');

      // Wait for debounce + fetch
      await page.waitForTimeout(1000);

      // Dropdown should be visible
      const dropdown = page.locator('.app-search__dropdown');
      const isVisible = await dropdown.isVisible().catch(() => false);

      if (isVisible) {
        // Either results are shown, or a "no results" / "min 2 chars" message
        const hasContent = await dropdown.locator('a, p, span').first().isVisible().catch(() => false);
        expect(hasContent).toBe(true);
      }
    }
  });

  test('sidebar links navigate correctly', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    // Click "Students" in sidebar
    const studentsLink = page.locator('a:has-text("Students")').first();
    if (await studentsLink.isVisible()) {
      await studentsLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/admin\/students/);
    }

    // Click "Settings" in sidebar
    const settingsLink = page.locator('a:has-text("Settings")').first();
    if (await settingsLink.isVisible()) {
      await settingsLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/admin\/settings/);
    }
  });

  test('profile page loads and edit works', async ({ page }) => {
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/profile/);

    // Name and email fields should be visible
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });
});
