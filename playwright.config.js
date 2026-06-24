import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E test configuration.
 *
 * Tests assume the Laravel app is running on http://localhost:8000.
 * Start it with: php artisan serve
 *
 * The test database should be seeded with:
 *   php artisan db:seed --force
 *
 * Default test credentials (from InitialDataSeeder):
 *   Admin:  admin@justspeak.test / password
 *   CFO:    cfo@justspeak.test / password
 *   Tutor:  tutor1@justspeak.test / password
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,  // Sequential — tests share a database
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,             // Single worker — E2E tests mutate shared state
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 15000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
