import { Page } from '@playwright/test';

export const WP_URL   = 'https://dev.widev.pro';
export const WP_USER  = 'admin';
export const WP_PASS  = '87412951';
export const LOC_URL  = `${WP_URL}/wp-admin/admin.php?page=edp-seo-locations`;

export async function wpLogin(page: Page): Promise<void> {
  await page.goto(`${WP_URL}/wp-login.php`);
  await page.fill('#user_login', WP_USER);
  await page.fill('#user_pass', WP_PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

export async function goToLocations(page: Page): Promise<void> {
  await page.goto(LOC_URL);
  await page.waitForSelector('.wp-list-table');
}

/** Returns the <tr> for the first row that has a "Create" button (no CPT yet). */
export async function firstRowWithCreate(page: Page) {
  return page.locator('tr:has(.edp-create-page-btn)').first();
}

/** Returns the <tr> for the first row that has a static page linked (cpt). */
export async function firstRowWithCpt(page: Page) {
  return page.locator('tr:has(.edp-static-page-cell)').first();
}

/** Returns the <tr> for the first row that has google data (not "Fetch" state). */
export async function firstRowWithGoogle(page: Page) {
  return page.locator('tr:has(.edp-listing-has-data)').first();
}
