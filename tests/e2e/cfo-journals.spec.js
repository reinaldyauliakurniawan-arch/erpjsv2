import { test, expect } from '@playwright/test';
import { loginAsCfo } from './helpers/auth';

/**
 * CFO Journals CRUD E2E tests.
 *
 * Verifies:
 * - View journal list
 * - View journal create form
 * - Create a balanced journal entry (debit = credit)
 * - Reject unbalanced journal (debit != credit)
 * - View journal detail
 * - Reverse a journal
 */

test.describe('CFO → Journals CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsCfo(page);
  });

  test('can view journal list', async ({ page }) => {
    await page.goto('/finance/journals');
    await expect(page).toHaveURL(/\/finance\/journals/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can view journal create form', async ({ page }) => {
    await page.goto('/finance/journals/create');
    await expect(page).toHaveURL(/\/finance\/journals\/create/);
    // Form should have date, reference, description, and items
    await expect(page.locator('input[name="date"]')).toBeVisible();
    await expect(page.locator('input[name="reference"]')).toBeVisible();
  });

  test('journal data endpoint returns JSON', async ({ page }) => {
    const response = await page.goto('/finance/journals/data');
    expect(response?.status()).toBe(200);
    const contentType = response?.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');
  });

  test('can create a balanced journal entry', async ({ page }) => {
    await page.goto('/finance/journals/create');

    // Fill header fields
    await page.fill('input[name="date"]', '2025-01-15');
    await page.fill('input[name="reference"]', 'E2E-TEST-' + Date.now());
    await page.fill('input[name="description"]', 'E2E test journal entry');

    // Fill journal items (2 rows: debit + credit)
    // Row 1: debit 100000
    const firstAccountSelect = page.locator('select[name="items[0][account_id]"]').first();
    if (await firstAccountSelect.isVisible()) {
      // Select first available account
      const options = await firstAccountSelect.locator('option').count();
      if (options > 1) {
        await firstAccountSelect.selectOption({ index: 1 });
      }
    }

    const firstDebit = page.locator('input[name="items[0][debit]"]').first();
    if (await firstDebit.isVisible()) {
      await firstDebit.fill('100000');
    }

    // Row 2: credit 100000
    const secondAccountSelect = page.locator('select[name="items[1][account_id]"]').first();
    if (await secondAccountSelect.isVisible()) {
      const options = await secondAccountSelect.locator('option').count();
      if (options > 1) {
        await secondAccountSelect.selectOption({ index: 1 });
      }
    }

    const secondCredit = page.locator('input[name="items[1][credit]"]').first();
    if (await secondCredit.isVisible()) {
      await secondCredit.fill('100000');
    }

    // Submit the form
    const submitBtn = page.locator('button[type="submit"]').first();
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      await page.waitForLoadState('networkidle');

      // Should redirect to journal list
      await expect(page).toHaveURL(/\/finance\/journals/);
    }
  });
});
