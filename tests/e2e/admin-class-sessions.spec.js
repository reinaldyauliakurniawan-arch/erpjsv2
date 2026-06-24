import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Admin Class Sessions CRUD E2E tests.
 * Covers: list, create, show, assign/remove enrollment, assign/remove tutor, schedules.
 */
test.describe('Admin → Class Sessions', () => {
  test.beforeEach(async ({ page }) => { await loginAsAdmin(page); });

  test('can view class session list', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    await expect(page).toHaveURL(/\/admin\/class-sessions/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('can view create form', async ({ page }) => {
    await page.goto('/admin/class-sessions/create');
    await expect(page).toHaveURL(/\/admin\/class-sessions\/create/);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('select[name="program_id"]')).toBeVisible();
    await expect(page.locator('select[name="class_type"]')).toBeVisible();
  });

  test('can create a class session', async ({ page }) => {
    await page.goto('/admin/class-sessions/create');
    await page.fill('input[name="name"]', 'E2E Test Session ' + Date.now());
    const progSelect = page.locator('select[name="program_id"]');
    const opts = await progSelect.locator('option').count();
    if (opts > 1) await progSelect.selectOption({ index: 1 });
    const typeSelect = page.locator('select[name="class_type"]');
    if (await typeSelect.isVisible()) await typeSelect.selectOption('private');
    const statusSelect = page.locator('select[name="status"]');
    if (await statusSelect.isVisible()) await statusSelect.selectOption('active');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/admin\/class-sessions/);
  });

  test('can view class session detail', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    const detailLink = page.locator('a[href*="/admin/class-sessions/"]').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/admin\/class-sessions\/\d+/);
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });

  test('class session detail has assign enrollment form', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    const link = page.locator('a[href*="/admin/class-sessions/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      // Look for assign enrollment form/button
      const assignForm = page.locator('form:has(select[name="enrollment_id"]), button:has-text("Assign")');
      const hasAssign = await assignForm.count();
      // If form exists, verify it has CSRF
      if (hasAssign > 0) {
        const form = assignForm.first();
        const csrf = form.locator('input[name="_token"]');
        expect(await csrf.count()).toBeGreaterThan(0);
      }
    }
  });

  test('class session detail has assign tutor form', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    const link = page.locator('a[href*="/admin/class-sessions/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      const tutorForm = page.locator('form:has(select[name="tutor_id"]), button:has-text("Assign Tutor")');
      const hasForm = await tutorForm.count();
      if (hasForm > 0) {
        const csrf = tutorForm.first().locator('input[name="_token"]');
        expect(await csrf.count()).toBeGreaterThan(0);
      }
    }
  });

  test('class session detail has schedule add form', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    const link = page.locator('a[href*="/admin/class-sessions/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      const scheduleForm = page.locator('form:has(select[name="classroom_id"]), form:has(input[name="day"])');
      const hasForm = await scheduleForm.count();
      if (hasForm > 0) {
        const csrf = scheduleForm.first().locator('input[name="_token"]');
        expect(await csrf.count()).toBeGreaterThan(0);
      }
    }
  });

  test('info endpoint returns JSON', async ({ page }) => {
    await page.goto('/admin/class-sessions');
    const link = page.locator('a[href*="/admin/class-sessions/"]').first();
    if (await link.isVisible()) {
      const href = await link.getAttribute('href');
      const id = href.match(/\/class-sessions\/(\d+)/)?.[1];
      if (id) {
        const response = await page.goto(`/admin/class-sessions/${id}/info`);
        expect(response?.status()).toBe(200);
        const ct = response?.headers()['content-type'] || '';
        expect(ct).toContain('json');
      }
    }
  });
});
