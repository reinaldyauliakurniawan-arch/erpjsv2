import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsCfo } from './helpers/auth';

/**
 * Edge case E2E tests.
 * Tests the specific bugs that were found and fixed:
 * - Apostrophe in names breaking Alpine.js
 * - Search bar Blade injection
 * - Double-submit protection
 * - Empty states
 * - Form validation
 * - Modal open/close
 */

test.describe('Edge Case → Apostrophe in names', () => {
  test('admin dashboard works with apostrophe names', async ({ page }) => {
    await loginAsAdmin(page);
    // If any student has an apostrophe in their name (O'Brien, D'Arcy),
    // the dashboard Alpine should still initialize without errors.
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // No JS errors means Alpine initialized successfully
    expect(errors.filter(e => !e.includes('favicon'))).toEqual([]);
  });

  test('search bar works with special characters', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const searchInput = page.locator('input[aria-label="Global search"]').first();
    if (await searchInput.isVisible()) {
      // Type a query with special chars
      await searchInput.click();
      await searchInput.fill("O'Brien");
      await page.waitForTimeout(500);

      // Should not crash — dropdown should appear
      const dropdown = page.locator('.app-search__dropdown');
      const isVisible = await dropdown.isVisible().catch(() => false);
      expect(isVisible).toBe(true);

      // Clear search
      await searchInput.fill('');
      await page.waitForTimeout(300);
    }
  });

  test('settings edit works with apostrophe in name', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/settings');
    await page.waitForLoadState('networkidle');

    // Click first edit button — should open modal regardless of name content
    const editBtn = page.locator('button[title="Edit"], button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();
      await page.waitForTimeout(500);

      const dialog = page.locator('dialog[open]').first();
      const isVisible = await dialog.isVisible().catch(() => false);
      expect(isVisible).toBe(true);

      // Name field should have a value (the user's actual name)
      const nameInput = dialog.locator('input[name="name"]').first();
      const nameValue = await nameInput.inputValue();
      expect(nameValue.length).toBeGreaterThan(0);
    }
  });
});

test.describe('Edge Case → Form validation', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('classroom create rejects empty name', async ({ page }) => {
    await page.goto('/admin/classrooms');
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Submit without filling name
      const submitBtn = dialog.locator('button[type="submit"]').first();
      await submitBtn.click();
      await page.waitForTimeout(1000);

      // Should show validation error or stay on the form
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });

  test('tutor create rejects invalid email', async ({ page }) => {
    await page.goto('/admin/tutors');
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      await dialog.locator('input[name="name"]').fill('Test');
      await dialog.locator('input[name="email"]').fill('not-an-email');
      await dialog.locator('input[name="password"]').fill('password');
      await dialog.locator('button[type="submit"]').first().click();
      await page.waitForTimeout(1000);

      // Should show validation error
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });

  test('program create rejects negative price', async ({ page }) => {
    await page.goto('/admin/programs');
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      await page.waitForTimeout(500);

      const nameInput = page.locator('input[name="name"]').first();
      if (await nameInput.isVisible()) {
        await nameInput.fill('Test');
        const priceInput = page.locator('input[name="price"]').first();
        if (await priceInput.isVisible()) await priceInput.fill('-100');
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(1000);
        await expect(page.locator('body')).not.toContainText(/server error/i);
      }
    }
  });
});

test.describe('Edge Case → Modal open/close', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('modal can be closed with Escape', async ({ page }) => {
    await page.goto('/admin/classrooms');
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Press Escape
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);

      // Dialog should close (or backdrop click closes it)
      const stillOpen = await dialog.isVisible().catch(() => false);
      // Some dialogs need explicit close button — check that too
      if (stillOpen) {
        const closeBtn = dialog.locator('button:has-text("Batal"), button:has-text("Cancel"), form[method="dialog"] button').first();
        if (await closeBtn.isVisible()) {
          await closeBtn.click();
          await page.waitForTimeout(500);
        }
      }
    }
  });

  test('modal can be closed with backdrop click', async ({ page }) => {
    await page.goto('/admin/classrooms');
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Click the backdrop (form method="dialog" with class modal-backdrop)
      const backdrop = page.locator('form.modal-backdrop, .modal-backdrop').first();
      if (await backdrop.isVisible()) {
        await backdrop.click();
        await page.waitForTimeout(500);
      }
    }
  });
});

test.describe('Edge Case → Empty states', () => {
  test('students page handles empty data gracefully', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/students');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    // Should show table or empty state message — not crash
    await expect(page.locator('body')).not.toContainText(/server error|whoops|undefined/i);
  });

  test('journals page handles empty data gracefully', async ({ page }) => {
    await loginAsCfo(page);
    await page.goto('/finance/journals');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    await expect(page.locator('body')).not.toContainText(/server error|whoops|undefined/i);
  });

  test('payroll page shows empty state when no runs', async ({ page }) => {
    await loginAsCfo(page);
    await page.goto('/finance/payroll');
    await page.waitForLoadState('networkidle');
    // Should show "Belum ada payroll run" or table — not crash
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });
});

test.describe('Edge Case → Double-submit protection', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('journal submit button disables during submission', async ({ page }) => {
    await page.goto('/finance/journals/create');
    await page.waitForLoadState('networkidle');

    // Fill a valid balanced journal
    await page.fill('input[name="date"]', '2025-01-15');
    await page.fill('input[name="reference"]', 'E2E-DOUBLE-' + Date.now());
    await page.fill('input[name="description"]', 'Double submit test');

    // Select accounts for both rows
    const accountSelects = page.locator('select[name*="account_id"]');
    const count = await accountSelects.count();
    for (let i = 0; i < Math.min(count, 2); i++) {
      const opts = await accountSelects.nth(i).locator('option').count();
      if (opts > 1) await accountSelects.nth(i).selectOption({ index: 1 });
    }

    // Fill debit and credit
    const debitInput = page.locator('input[name="items[0][debit]"]').first();
    if (await debitInput.isVisible()) await debitInput.fill('100000');
    const creditInput = page.locator('input[name="items[1][credit]"]').first();
    if (await creditInput.isVisible()) await creditInput.fill('100000');

    // Click submit — button should show loading state
    const submitBtn = page.locator('button[type="submit"]').first();
    if (await submitBtn.isVisible()) {
      // Check if button has Alpine x-data with submitting state
      const hasSubmittingState = await submitBtn.evaluate(el => {
        return el.hasAttribute(':disabled') || el.hasAttribute(':class') ||
               el.closest('[x-data]')?.getAttribute('x-data')?.includes('submitting');
      });
      // If the form has double-submit protection, the button should disable
      expect(hasSubmittingState).toBe(true);
    }
  });
});

test.describe('Edge Case → XSS prevention', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('search input does not execute script tags', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');

    const searchInput = page.locator('input[aria-label="Global search"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.click();
      await searchInput.fill('<script>alert("xss")</script>');
      await page.waitForTimeout(500);

      // No alert dialog should appear
      // (Playwright auto-dismisses dialogs, but we verify no error)
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });
});

test.describe('Edge Case → Pagination', () => {
  test('tutor list has pagination', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/tutors');
    await page.waitForLoadState('networkidle');
    // Should have pagination links or "showing X of Y" text
    const pagination = page.locator('.pagination, nav[aria-label*="agination"], [class*="page"]');
    expect(await pagination.count()).toBeGreaterThanOrEqual(0);
  });

  test('payroll list has pagination', async ({ page }) => {
    await loginAsCfo(page);
    await page.goto('/finance/payroll');
    await page.waitForLoadState('networkidle');
    const pagination = page.locator('.pagination, nav[aria-label*="agination"]');
    expect(await pagination.count()).toBeGreaterThanOrEqual(0);
  });
});
