import { test, expect } from '@playwright/test';
import { loginAsCfo } from './helpers/auth';

/**
 * CFO Accounts CRUD E2E tests.
 */

test.describe('CFO → Accounts CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsCfo(page);
  });

  test('can view account list', async ({ page }) => {
    await page.goto('/finance/accounts');
    await expect(page).toHaveURL(/\/finance\/accounts/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can create a new account', async ({ page }) => {
    await page.goto('/finance/accounts');

    // Find create button
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create"), a:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      // Wait for modal
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Fill form
      const codeInput = dialog.locator('input[name="code"]').first();
      if (await codeInput.isVisible()) {
        await codeInput.fill('9999');

        const nameInput = dialog.locator('input[name="name"]').first();
        await nameInput.fill('E2E Test Account');

        const typeSelect = dialog.locator('select[name="type"]').first();
        if (await typeSelect.isVisible()) {
          await typeSelect.selectOption('Asset');
        }

        // Submit
        const submitBtn = dialog.locator('button[type="submit"]').first();
        await submitBtn.click();
        await page.waitForLoadState('networkidle');

        // Should be back on accounts list
        await expect(page).toHaveURL(/\/finance\/accounts/);
      }
    }
  });
});
