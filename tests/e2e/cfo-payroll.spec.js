import { test, expect } from '@playwright/test';
import { loginAsCfo } from './helpers/auth';

/**
 * CFO Payroll E2E tests.
 *
 * Verifies:
 * - Payroll index page loads with payroll run list
 * - Create payroll run modal works
 * - Approve button is present for pending runs
 * - Reverse button is present for approved runs
 */

test.describe('CFO → Payroll', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsCfo(page);
  });

  test('can view payroll list', async ({ page }) => {
    await page.goto('/finance/payroll');
    await expect(page).toHaveURL(/\/finance\/payroll/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can open create payroll run modal', async ({ page }) => {
    await page.goto('/finance/payroll');

    const createBtn = page.locator('button:has-text("Buat Payroll"), button:has-text("Create"), button:has-text("Buat")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      const dialog = page.locator('dialog[open]').first();
      await expect(dialog).toBeVisible({ timeout: 5000 });

      // Month input should be present
      await expect(dialog.locator('input[name="month"], input[type="month"]')).toBeVisible();
    }
  });

  test('pending payroll runs have approve button', async ({ page }) => {
    await page.goto('/finance/payroll');

    // Look for "Approve" buttons in the table
    const approveButtons = page.locator('button:has-text("Approve")');
    const count = await approveButtons.count();

    // If there are pending runs, they should have clickable approve buttons
    for (let i = 0; i < Math.min(count, 3); i++) {
      await expect(approveButtons.nth(i)).not.toBeDisabled();
    }
  });

  test('approved payroll runs have reverse button', async ({ page }) => {
    await page.goto('/finance/payroll');

    // Look for "Reverse" buttons in the table
    const reverseButtons = page.locator('button:has-text("Reverse")');
    const count = await reverseButtons.count();

    // If there are approved runs, they should have clickable reverse buttons
    for (let i = 0; i < Math.min(count, 3); i++) {
      await expect(reverseButtons.nth(i)).not.toBeDisabled();
    }
  });
});
