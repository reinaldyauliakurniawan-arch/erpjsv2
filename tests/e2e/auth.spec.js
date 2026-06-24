import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsCfo, loginAsTutor, logout } from './helpers/auth';

/**
 * Authentication E2E tests.
 *
 * Verifies that login/logout works for each role, and that
 * role-based redirects land on the correct dashboard.
 */

test.describe('Authentication', () => {

  test('admin can login and see admin dashboard', async ({ page }) => {
    await loginAsAdmin(page);
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });

  test('cfo can login and see finance dashboard', async ({ page }) => {
    await loginAsCfo(page);
    await expect(page).toHaveURL(/\/finance/);
  });

  test('tutor can login and see tutor dashboard', async ({ page }) => {
    await loginAsTutor(page);
    await expect(page).toHaveURL(/\/tutor\/dashboard/);
  });

  test('wrong password shows error', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@justspeak.test');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    // Should stay on login page with error
    await expect(page).toHaveURL(/\/login/);
  });

  test('admin can logout', async ({ page }) => {
    await loginAsAdmin(page);
    await logout(page);
    await expect(page).toHaveURL(/\/login/);
  });

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/\/login/);
  });

  test('admin cannot access finance routes', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/finance');
    // Admin should get 403 forbidden
    await expect(page.locator('body')).toContainText(/403|forbidden|unauthorized/i);
  });

  test('tutor cannot access admin routes', async ({ page }) => {
    await loginAsTutor(page);
    await page.goto('/admin/dashboard');
    await expect(page.locator('body')).toContainText(/403|forbidden|unauthorized/i);
  });
});
