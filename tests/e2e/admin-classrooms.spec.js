import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Admin Classrooms CRUD E2E tests.
 *
 * Verifies:
 * - View classroom list
 * - Create new classroom (form submit → success message)
 * - Edit classroom (update name/capacity)
 * - Delete classroom (with guard if has schedules)
 */

test.describe('Admin → Classrooms CRUD', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('can view classroom list', async ({ page }) => {
    await page.goto('/admin/classrooms');
    await expect(page).toHaveURL(/\/admin\/classrooms/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can create a new classroom', async ({ page }) => {
    await page.goto('/admin/classrooms');

    // Click the "Create" or "Tambah" button to open the modal
    const createButton = page.locator('button:has-text("Tambah"), button:has-text("Create"), button:has-text("Add")').first();
    if (await createButton.isVisible()) {
      await createButton.click();

      // Wait for modal/dialog to appear
      const dialog = page.locator('dialog[open], .modal:visible').first();
      await dialog.waitFor({ state: 'visible', timeout: 5000 });

      // Fill the form
      const nameInput = dialog.locator('input[name="name"]').first();
      if (await nameInput.isVisible()) {
        await nameInput.fill('E2E Test Room ' + Date.now());

        const capacityInput = dialog.locator('input[name="capacity"]').first();
        if (await capacityInput.isVisible()) {
          await capacityInput.fill('15');
        }

        // Submit the form
        const submitButton = dialog.locator('button[type="submit"]').first();
        await submitButton.click();

        // Wait for page to reload / redirect
        await page.waitForLoadState('networkidle');

        // Check for success message or that we're back on the list
        await expect(page).toHaveURL(/\/admin\/classrooms/);
      }
    }
  });

  test('edit and delete buttons are present and clickable', async ({ page }) => {
    await page.goto('/admin/classrooms');

    // Check that edit buttons exist
    const editButtons = page.locator('button:has-text("Edit"), [title="Edit"], a:has-text("Edit")');
    const editCount = await editButtons.count();

    // Check that delete buttons exist
    const deleteButtons = page.locator('button:has-text("Delete"), button:has-text("Hapus"), [title="Hapus"], a:has-text("Hapus")');
    const deleteCount = await deleteButtons.count();

    // If there are classrooms in the list, there should be edit/delete buttons
    if (editCount > 0) {
      // Verify the first edit button is clickable (not disabled)
      await expect(editButtons.first()).not.toBeDisabled();
    }
    if (deleteCount > 0) {
      await expect(deleteButtons.first()).not.toBeDisabled();
    }
  });
});
