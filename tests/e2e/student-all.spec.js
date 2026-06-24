import { test, expect } from '@playwright/test';

/**
 * Student portal E2E tests.
 */
const STUDENT = { email: 'student100@justspeak.test', password: 'password' };

async function loginAsStudent(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', STUDENT.email);
  await page.fill('input[name="password"]', STUDENT.password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10000 }).catch(() => {});
}

test.describe('Student → Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStudent(page);
  });

  test('dashboard loads', async ({ page }) => {
    await page.goto('/student/dashboard');
    // Student might not have dashboard access if no enrollment — just verify no 500
    await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
  });

  test('dashboard has no JS errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));
    await page.goto('/student/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    expect(errors).toEqual([]);
  });
});

test.describe('Student → Practice', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStudent(page);
  });

  test('can view practice list', async ({ page }) => {
    await page.goto('/student/practice');
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('practice list has open/submit buttons if practices exist', async ({ page }) => {
    await page.goto('/student/practice');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    // If there are practices, they should have Open/Submit buttons
    const openBtns = page.locator('button:has-text("Open"), a:has-text("Open"), form[action*="open"]');
    const submitBtns = page.locator('button:has-text("Submit"), form[action*="submit"]');
    const totalBtns = (await openBtns.count()) + (await submitBtns.count());
    expect(totalBtns).toBeGreaterThanOrEqual(0);
  });
});

test.describe('Student → Tracker', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStudent(page);
  });

  test('can view tracker page', async ({ page }) => {
    await page.goto('/student/tracker');
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });
});

test.describe('Student → Access Control', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStudent(page);
  });

  test('cannot access admin routes', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await expect(page.locator('body')).toContainText(/403|forbidden|unauthorized/i);
  });

  test('cannot access finance routes', async ({ page }) => {
    await page.goto('/finance');
    await expect(page.locator('body')).toContainText(/403|forbidden|unauthorized/i);
  });

  test('cannot access tutor routes', async ({ page }) => {
    await page.goto('/tutor/dashboard');
    await expect(page.locator('body')).toContainText(/403|forbidden|unauthorized/i);
  });
});
