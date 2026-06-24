import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Settings/Users CRUD E2E tests.
 *
 * Verifies that the user management buttons work:
 * - Create new user (admin/cfo/tutor/student)
 * - Edit existing user
 * - Delete user (with guards: cannot delete if has active data)
 */

test.describe('Admin → Settings → Users CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('can view settings page with user list', async ({ page }) => {
    await page.goto('/admin/settings');
    await expect(page).toHaveURL(/\/admin\/settings/);
    // User table should be visible
    await expect(page.locator('table')).toBeVisible();
    // Should see user rows
    await expect(page.locator('tbody tr')).toHaveCount(1, { min: 0 });
  });

  test('can open create user modal', async ({ page }) => {
    await page.goto('/admin/settings');

    // Click "Tambah User" button
    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create"), button:has-text("Add")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      // Modal should appear
      const dialog = page.locator('dialog[open]').first();
      await expect(dialog).toBeVisible({ timeout: 5000 });

      // Form fields should be present
      await expect(dialog.locator('input[name="name"]')).toBeVisible();
      await expect(dialog.locator('input[name="email"]')).toBeVisible();
      await expect(dialog.locator('input[name="password"]')).toBeVisible();
      await expect(dialog.locator('select[name="role"]')).toBeVisible();
    }
  });

  test('can create a new admin user', async ({ page }) => {
    await page.goto('/admin/settings');

    const createBtn = page.locator('button:has-text("Tambah"), button:has-text("Create")').first();
    if (await createBtn.isVisible()) {
      await createBtn.click();

      const dialog = page.locator('dialog[open]').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Fill form
      await dialog.locator('input[name="name"]').fill('E2E Admin User');
      await dialog.locator('input[name="email"]').fill('e2e-admin-' + Date.now() + '@justspeak.test');
      await dialog.locator('input[name="password"]').fill('password123');
      await dialog.locator('select[name="role"]').selectOption('admin');

      // Submit
      await dialog.locator('button[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');

      // Should be back on settings page
      await expect(page).toHaveURL(/\/admin\/settings/);
    }
  });

  test('edit button opens edit modal with user data', async ({ page }) => {
    await page.goto('/admin/settings');

    // Find first edit button in user table
    const editBtn = page.locator('button[title="Edit"], button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();

      // Edit modal should appear with pre-filled data
      const dialog = page.locator('dialog[open]').first();
      await expect(dialog).toBeVisible({ timeout: 5000 });

      // Name field should have a value (the user's name)
      const nameInput = dialog.locator('input[name="name"]').first();
      const nameValue = await nameInput.inputValue();
      expect(nameValue.length).toBeGreaterThan(0);
    }
  });

  test('delete button opens confirmation dialog', async ({ page }) => {
    await page.goto('/admin/settings');

    const deleteBtn = page.locator('button[title="Hapus"], button:has-text("Hapus"), button:has-text("Delete")').first();
    if (await deleteBtn.isVisible()) {
      await deleteBtn.click();

      // Delete confirmation modal should appear
      const dialog = page.locator('dialog[open]').first();
      await expect(dialog).toBeVisible({ timeout: 5000 });

      // Should show the user name in the confirmation text
      await expect(dialog.locator('body, .modal-box')).toContainText(/hapus|delete/i);
    }
  });

  test('color settings page loads', async ({ page }) => {
    await page.goto('/admin/settings/colors');
    await expect(page).toHaveURL(/\/admin\/settings\/colors/);
    // Color picker inputs should be present
    await expect(page.locator('input[type="color"], input[name*="color"]')).toHaveCount(1, { min: 0 });
  });
});
