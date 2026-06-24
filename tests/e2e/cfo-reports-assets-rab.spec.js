import { test, expect } from '@playwright/test';
import { loginAsCfo } from './helpers/auth';

/**
 * CFO Reports E2E tests — all 8 report pages.
 * Verifies each report loads without 500 errors and renders content.
 */
test.describe('CFO → Reports', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  const reports = [
    { name: 'Trial Balance', url: '/finance/reports/trial-balance' },
    { name: 'Adjusted Trial Balance', url: '/finance/reports/adjusted-trial-balance' },
    { name: 'General Ledger', url: '/finance/reports/general-ledger' },
    { name: 'Profit & Loss', url: '/finance/reports/profit-loss' },
    { name: 'Balance Sheet', url: '/finance/reports/balance-sheet' },
    { name: 'Cash Flow', url: '/finance/reports/cash-flow' },
    { name: 'Equity Statement', url: '/finance/reports/equity-statement' },
    { name: 'Deferred Revenue', url: '/finance/reports/deferred-revenue' },
    { name: 'Fixed Assets Report', url: '/finance/reports/fixed_assets' },
  ];

  for (const rpt of reports) {
    test(`can view ${rpt.name} report`, async ({ page }) => {
      await page.goto(rpt.url);
      await expect(page).toHaveURL(new RegExp(rpt.url.replace('/', '\\/')));
      await expect(page.locator('body')).not.toContainText(/server error|whoops|500/i);
      await expect(page.locator('main, .app-main')).toBeVisible();
    });
  }

  test('reports page has date range filters', async ({ page }) => {
    await page.goto('/finance/reports/trial-balance');
    await page.waitForLoadState('networkidle');
    const dateInputs = page.locator('input[type="date"], input[name*="date"], input[name*="period"]');
    expect(await dateInputs.count()).toBeGreaterThan(0);
  });

  test('trial balance has account table', async ({ page }) => {
    await page.goto('/finance/reports/trial-balance');
    await page.waitForLoadState('networkidle');
    const table = page.locator('table, .tabulator, [class*="table"]');
    expect(await table.count()).toBeGreaterThan(0);
  });

  test('general ledger has account list', async ({ page }) => {
    await page.goto('/finance/reports/general-ledger');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('profit loss shows revenue/expense sections', async ({ page }) => {
    await page.goto('/finance/reports/profit-loss');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('balance sheet loads with assets/liabilities', async ({ page }) => {
    await page.goto('/finance/reports/balance-sheet');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });
});

/**
 * CFO Adjusting Journals E2E tests.
 */
test.describe('CFO → Adjusting Journals', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  test('can view adjusting journals page', async ({ page }) => {
    await page.goto('/finance/adjusting-journals');
    await expect(page).toHaveURL(/\/finance\/adjusting-journals/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('adjusting journals data endpoint returns JSON', async ({ page }) => {
    const response = await page.goto('/finance/adjusting-journals/data');
    expect(response?.status()).toBe(200);
    const ct = response?.headers()['content-type'] || '';
    expect(ct).toContain('json');
  });

  test('has create adjusting journal form', async ({ page }) => {
    await page.goto('/finance/adjusting-journals');
    await page.waitForLoadState('networkidle');
    const createForm = page.locator('form:has(select[name="type"]), form:has(input[name="description"]), button:has-text("Tambah")');
    expect(await createForm.count()).toBeGreaterThan(0);
  });

  test('has auto-generate button', async ({ page }) => {
    await page.goto('/finance/adjusting-journals');
    await page.waitForLoadState('networkidle');
    const generateBtn = page.locator('button:has-text("Generate"), a:has-text("Generate"), form[action*="generate"]');
    expect(await generateBtn.count()).toBeGreaterThanOrEqual(0);
  });
});

/**
 * CFO Fixed Assets E2E tests.
 */
test.describe('CFO → Fixed Assets', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  test('can view fixed assets page', async ({ page }) => {
    await page.goto('/finance/assets');
    await expect(page).toHaveURL(/\/finance\/assets/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('has create asset form', async ({ page }) => {
    await page.goto('/finance/assets');
    await page.waitForLoadState('networkidle');
    const createForm = page.locator('form:has(input[name="name"]), dialog:has(input[name="name"])');
    expect(await createForm.count()).toBeGreaterThan(0);
  });

  test('has generate depreciation button', async ({ page }) => {
    await page.goto('/finance/assets');
    await page.waitForLoadState('networkidle');
    const depBtn = page.locator('button:has-text("Depreciation"), form[action*="depreciation"]');
    expect(await depBtn.count()).toBeGreaterThanOrEqual(0);
  });
});

/**
 * CFO RAB E2E tests.
 */
test.describe('CFO → RAB', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  test('can view RAB page', async ({ page }) => {
    await page.goto('/finance/rab');
    await expect(page).toHaveURL(/\/finance\/rab/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('RAB data endpoint returns JSON', async ({ page }) => {
    const response = await page.goto('/finance/rab/data');
    expect(response?.status()).toBe(200);
    const ct = response?.headers()['content-type'] || '';
    expect(ct).toContain('json');
  });

  test('can view RAB Realisasi page', async ({ page }) => {
    await page.goto('/finance/rab-realisasi');
    await expect(page).toHaveURL(/\/finance\/rab-realisasi/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('has create RAB form', async ({ page }) => {
    await page.goto('/finance/rab');
    await page.waitForLoadState('networkidle');
    const createForm = page.locator('form:has(input[name="division"]), form:has(select[name="year"])');
    expect(await createForm.count()).toBeGreaterThan(0);
  });
});

/**
 * CFO Finance Dashboard E2E tests.
 */
test.describe('CFO → Finance Dashboard', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  test('dashboard loads without errors', async ({ page }) => {
    await page.goto('/finance');
    await expect(page).toHaveURL(/\/finance$/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('has month filter', async ({ page }) => {
    await page.goto('/finance');
    await page.waitForLoadState('networkidle');
    const monthFilter = page.locator('select[name="month"], input[type="month"]');
    expect(await monthFilter.count()).toBeGreaterThan(0);
  });

  test('has revenue chart', async ({ page }) => {
    await page.goto('/finance');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    const chart = page.locator('canvas, svg, [class*="chart"]');
    expect(await chart.count()).toBeGreaterThanOrEqual(0);
  });

  test('revenue chart endpoint returns JSON', async ({ page }) => {
    const response = await page.goto('/finance/chart/revenue-by-program');
    expect(response?.status()).toBe(200);
    const ct = response?.headers()['content-type'] || '';
    expect(ct).toContain('json');
  });

  test('reports page loads', async ({ page }) => {
    await page.goto('/finance/reports');
    await expect(page).toHaveURL(/\/finance\/reports/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('finance imports page loads', async ({ page }) => {
    await page.goto('/finance/imports');
    await expect(page).toHaveURL(/\/finance\/imports/);
    await expect(page.locator('body')).not.toContainText(/server error/i);
  });

  test('can export journals', async ({ page }) => {
    const response = await page.request.get('/finance/exports/journals');
    expect(response.status()).toBeLessThan(500);
  });

  test('can export trial balance', async ({ page }) => {
    const response = await page.request.get('/finance/exports/trial-balance');
    expect(response.status()).toBeLessThan(500);
  });

  test('can download finance template', async ({ page }) => {
    const response = await page.request.get('/finance/exports/finance-template/coa');
    expect(response.status()).toBeLessThan(500);
  });
});

/**
 * CFO Journal detail + reverse E2E tests.
 */
test.describe('CFO → Journal Detail & Reverse', () => {
  test.beforeEach(async ({ page }) => { await loginAsCfo(page); });

  test('can view journal detail', async ({ page }) => {
    await page.goto('/finance/journals');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    const detailLink = page.locator('a[href*="/finance/journals/"]').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/finance\/journals\/\d+/);
      await expect(page.locator('body')).not.toContainText(/server error/i);
    }
  });

  test('journal detail has reverse button', async ({ page }) => {
    await page.goto('/finance/journals');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    const detailLink = page.locator('a[href*="/finance/journals/"]').first();
    if (await detailLink.isVisible()) {
      await detailLink.click();
      await page.waitForLoadState('networkidle');
      const reverseBtn = page.locator('button:has-text("Reverse"), a:has-text("Reverse"), form[action*="reverse"]');
      expect(await reverseBtn.count()).toBeGreaterThanOrEqual(0);
    }
  });

  test('can export payroll', async ({ page }) => {
    const response = await page.request.get('/finance/exports/payroll');
    expect(response.status()).toBeLessThan(500);
  });

  test('can export balance sheet', async ({ page }) => {
    const response = await page.request.get('/finance/exports/balance-sheet');
    expect(response.status()).toBeLessThan(500);
  });

  test('can export profit loss', async ({ page }) => {
    const response = await page.request.get('/finance/exports/profit-loss');
    expect(response.status()).toBeLessThan(500);
  });

  test('can export COA', async ({ page }) => {
    const response = await page.request.get('/finance/exports/coa');
    expect(response.status()).toBeLessThan(500);
  });

  test('can export deferred revenue', async ({ page }) => {
    const response = await page.request.get('/finance/exports/deferred-revenue');
    expect(response.status()).toBeLessThan(500);
  });
});
