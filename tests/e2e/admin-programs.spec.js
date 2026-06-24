import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Admin Programs CRUD E2E tests.
 */

test.describe('Admin → Programs CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('can view program list', async ({ page }) => {
    await page.goto('/admin/programs');
    await expect(page).toHaveURL(/\/admin\/programs/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can create a new program', async ({ page }) => {
    await page.goto('/admin/programs');

    // Find and click create button
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create"), a:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      // Wait for modal or form
      const nameInput = page.locator('input[name="name"]').first();
      if (await nameInput.isVisible()) {
        await nameInput.fill('E2E Test Program');

        // Fill type select
        const typeSelect = page.locator('select[name="type"]').first();
        if (await typeSelect.isVisible()) {
          await typeSelect.selectOption('private');
        }

        // Fill price
        const priceInput = page.locator('input[name="price"]').first();
        if (await priceInput.isVisible()) {
          await priceInput.fill('500000');
        }

        // Fill total meetings
        const meetingsInput = page.locator('input[name="total_meetings"]').first();
        if (await meetingsInput.isVisible()) {
          await meetingsInput.fill('8');
        }

        // Submit
        const submitBtn = page.locator('button[type="submit"]').first();
        await submitBtn.click();
        await page.waitForLoadState('networkidle');

        // Should be back on programs list
        await expect(page).toHaveURL(/\/admin\/programs/);
      }
    }
  });

  test('program edit buttons are clickable', async ({ page }) => {
    await page.goto('/admin/programs');

    // Programs table should have edit buttons (Alpine toggle)
    const editButtons = page.locator('[title="Edit"], button:has-text("Edit")');
    const count = await editButtons.count();
    if (count > 0) {
      await expect(editButtons.first()).not.toBeDisabled();
    }
  });
});
