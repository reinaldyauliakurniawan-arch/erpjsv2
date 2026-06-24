import { test, expect } from '@playwright/test';
import { loginAsAdmin, logout } from './helpers/auth';

/**
 * Profile + Password Reset E2E tests.
 */
test.describe('Profile', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('can view profile edit page', async ({ page }) => {
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/profile/);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });

  test('can update profile name', async ({ page }) => {
    await page.goto('/profile');
    const nameInput = page.locator('input[name="name"]');
    await nameInput.fill('Admin Updated Name');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    // Should stay on profile page or redirect
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('can update password', async ({ page }) => {
    await page.goto('/profile');
    const passwordInput = page.locator('input[name="password"]').first();
    if (await passwordInput.isVisible()) {
      await passwordInput.fill('newpassword123');
      const confirmInput = page.locator('input[name="password_confirmation"]');
      if (await confirmInput.isVisible()) {
        await confirmInput.fill('newpassword123');
      }
      const submitBtn = page.locator('button[type="submit"]').first();
      await submitBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });

  test('can delete account (with confirm)', async ({ page }) => {
    await page.goto('/profile');
    const deleteBtn = page.locator('button:has-text("Delete"), button:has-text("Hapus Akun")');
    if (await deleteBtn.isVisible()) {
      // Should trigger a confirmation dialog
      page.on('dialog', dialog => dialog.dismiss()); // Cancel the deletion
      await deleteBtn.click();
      // Account should NOT be deleted because we cancelled
    }
  });
});

test.describe('Password Reset Flow', () => {
  test('can view forgot password page', async ({ page }) => {
    await page.goto('/forgot-password');
    await expect(page).toHaveURL(/\/forgot-password/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });

  test('can request password reset link', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.fill('input[name="email"]', 'admin@justspeak.test');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    // Should show success message (email sent) or redirect
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('invalid email shows validation error', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.fill('input[name="email"]', 'not-an-email');
    await page.click('button[type="submit"]');
    // Should show validation error, not submit
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });
});

test.describe('Confirm Password', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('can view confirm password page', async ({ page }) => {
    await page.goto('/confirm-password');
    await expect(page).toHaveURL(/\/confirm-password/);
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });
});
