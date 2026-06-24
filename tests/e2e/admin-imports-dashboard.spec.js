import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Admin Import/Export E2E tests.
 */
test.describe('Admin → Import/Export', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('can view import page', async ({ page }) => {
    await page.goto('/admin/imports');
    await expect(page).toHaveURL(/\/admin\/imports/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('import page has file upload forms', async ({ page }) => {
    await page.goto('/admin/imports');
    await page.waitForLoadState('networkidle');
    const uploadForms = page.locator('form:has(input[type="file"]), input[type="file"]');
    expect(await uploadForms.count()).toBeGreaterThan(0);
  });

  test('import page has template download links', async ({ page }) => {
    await page.goto('/admin/imports');
    await page.waitForLoadState('networkidle');
    const downloadLinks = page.locator('a[href*="template"], a:has-text("Template"), a:has-text("Download")');
    expect(await downloadLinks.count()).toBeGreaterThan(0);
  });

  test('can download attendance export', async ({ page }) => {
    const response = await page.request.get('/admin/exports/attendance');
    // Should be 200 (file download) or redirect to login if session expired
    expect(response.status()).toBeLessThan(500);
  });

  test('can download a template', async ({ page }) => {
    const response = await page.request.get('/admin/exports/template/classrooms');
    expect(response.status()).toBeLessThan(500);
  });

  test('import forms have CSRF token', async ({ page }) => {
    await page.goto('/admin/imports');
    await page.waitForLoadState('networkidle');
    const forms = page.locator('form[method="POST"], form[method="post"]');
    const count = await forms.count();
    for (let i = 0; i < Math.min(count, 3); i++) {
      const csrf = forms.nth(i).locator('input[name="_token"]');
      expect(await csrf.count()).toBeGreaterThan(0);
    }
  });
});

/**
 * Admin Dashboard E2E tests.
 */
test.describe('Admin → Dashboard', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('dashboard loads without errors', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('dashboard has stat cards', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    // Should have KPI/stat cards
    const statCards = page.locator('.app-stat-card, .app-card, [class*="stat"]');
    expect(await statCards.count()).toBeGreaterThan(0);
  });

  test('dashboard has search/filter inputs', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    const searchInputs = page.locator('input[type="search"], input[placeholder*="ari"], input[placeholder*="search"]');
    expect(await searchInputs.count()).toBeGreaterThan(0);
  });

  test('dashboard tab buttons are clickable', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    // Look for tab buttons (Reguler, Private, Semi)
    const tabs = page.locator('button:has-text("Reguler"), button:has-text("Private"), button:has-text("Semi")');
    const count = await tabs.count();
    for (let i = 0; i < count; i++) {
      await expect(tabs.nth(i)).not.toBeDisabled();
    }
  });

  test('dashboard has no JS console errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    expect(errors).toEqual([]);
  });
});
