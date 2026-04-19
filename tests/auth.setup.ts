/**
 * One-time auth setup: logs into WP admin and saves cookies to disk.
 * Playwright reuses the saved state in every test so login runs only once.
 */
import { test as setup } from '@playwright/test';
import { WP_URL, WP_USER, WP_PASS } from './helpers';

export const AUTH_FILE = 'tests/.auth/state.json';

setup('authenticate', async ({ page }) => {
  await page.goto(`${WP_URL}/wp-login.php`);
  await page.fill('#user_login', WP_USER);
  await page.fill('#user_pass', WP_PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
  await page.context().storageState({ path: AUTH_FILE });
});
