/**
 * Shared test helpers for E2E tests.
 *
 * These helpers handle login/logout flows that are used across
 * multiple test files. Keeping them in one place avoids duplication
 * and makes it easy to update the login flow if the UI changes.
 */

const ADMIN = {
  email: 'admin@justspeak.test',
  password: 'password',
};

const CFO = {
  email: 'cfo@justspeak.test',
  password: 'password',
};

const TUTOR = {
  email: 'tutor1@justspeak.test',
  password: 'password',
};

/**
 * Login as a specific role.
 * Navigates to /login, fills the form, submits, and waits for redirect.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{email: string, password: string}} credentials
 */
async function login(page, credentials) {
  await page.goto('/login');
  await page.fill('input[name="email"]', credentials.email);
  await page.fill('input[name="password"]', credentials.password);
  await page.click('button[type="submit"]');
  // Wait for redirect away from /login
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10000 });
}

/**
 * Login as admin.
 */
async function loginAsAdmin(page) {
  await login(page, ADMIN);
}

/**
 * Login as CFO.
 */
async function loginAsCFO(page) {
  await login(page, CFO);
}

/**
 * Login as tutor.
 */
async function loginAsTutor(page) {
  await login(page, TUTOR);
}

/**
 * Logout via the sidebar Sign Out button.
 */
async function logout(page) {
  // Find the Sign Out form in the sidebar and submit it
  const logoutButton = page.locator('button:has-text("Sign Out"), button:has-text("Logout")');
  if (await logoutButton.isVisible()) {
    await logoutButton.click();
    await page.waitForURL('**/login', { timeout: 10000 });
  }
}

/**
 * Dismiss any flash messages (success/error alerts) that might
 * interfere with clicking elements below them.
 */
async function dismissFlashMessages(page) {
  const alerts = page.locator('.alert, [role="alert"]');
  const count = await alerts.count();
  for (let i = 0; i < count; i++) {
    await alerts.nth(0).click({ force: true }).catch(() => {});
  }
}

/**
 * Wait for a success flash message to appear.
 * @param {import('@playwright/test').Page} page
 * @param {string} text - partial text to match
 */
async function expectSuccessMessage(page, text) {
  const alert = page.locator('.alert-success, .alert:has-text("berhasil"), [role="alert"]:has-text("' + text + '")');
  await alert.waitFor({ state: 'visible', timeout: 5000 });
}

/**
 * Wait for an error flash message to appear.
 * @param {import('@playwright/test').Page} page
 * @param {string} text - partial text to match
 */
async function expectErrorMessage(page, text) {
  const alert = page.locator('.alert-error, .alert:has-text("' + text + '"), [role="alert"]:has-text("' + text + '")');
  await alert.waitFor({ state: 'visible', timeout: 5000 });
}

module.exports = {
  ADMIN,
  CFO,
  TUTOR,
  login,
  loginAsAdmin,
  loginAsCFO,
  loginAsTutor,
  logout,
  dismissFlashMessages,
  expectSuccessMessage,
  expectErrorMessage,
};
