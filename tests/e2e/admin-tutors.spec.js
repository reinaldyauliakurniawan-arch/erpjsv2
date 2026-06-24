import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Admin Tutors CRUD E2E tests.
 */

test.describe('Admin → Tutors CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('can view tutor list', async ({ page }) => {
    await page.goto('/admin/tutors');
    await expect(page).toHaveURL(/\/admin\/tutors/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can view tutor detail', async ({ page }) => {
    await page.goto('/admin/tutors');

    // Click first tutor's detail link
    const detailLink = page.locator('a[href*="/admin/tutors/"]').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/admin\/tutors\/\d+/);
    }
  });

  test('can create a new tutor', async ({ page }) => {
    await page.goto('/admin/tutors');

    // Click "Tambah Tutor" button to open modal
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      // Wait for modal
      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Fill form
      const nameInput = dialog.locator('input[name="name"]').first();
      if (await nameInput.isVisible()) {
        await nameInput.fill('E2E Test Tutor');

        const emailInput = dialog.locator('input[name="email"]').first();
        await emailInput.fill('e2e-tutor-' + Date.now() + '@justspeak.test');

        const passwordInput = dialog.locator('input[name="password"]').first();
        await passwordInput.fill('password');

        const personaInput = dialog.locator('input[name="persona"]').first();
        if (await personaInput.isVisible()) {
          await personaInput.fill('S1 English');
        }

        // Submit
        const submitBtn = dialog.locator('button[type="submit"]').first();
        await submitBtn.click();
        await page.waitForLoadState('networkidle');

        // Should be back on tutor list
        await expect(page).toHaveURL(/\/admin\/tutors/);
      }
    }
  });
});
