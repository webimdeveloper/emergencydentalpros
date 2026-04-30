/**
 * Page cache diagnostics — runs as a guest (no stored auth state).
 */

import { test, expect } from '@playwright/test';
import { WP_URL, WP_USER, WP_PASS } from './helpers';

// ── no auth state — all requests are guest ────────────────────────────────────
test.use({ storageState: { cookies: [], origins: [] } });

const CITY_PATH = '/locations/massachusetts/agawam-ma/';
const ADMIN_CACHE_URL = `${WP_URL}/wp-admin/admin.php?page=edp-seo`;

// ── 1. Enable cache via admin ─────────────────────────────────────────────────
test('enable cache in admin and verify setting is saved', async ({ browser }) => {
  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();

  // Log in
  await adminPage.goto(`${WP_URL}/wp-login.php`);
  await adminPage.fill('#user_login', WP_USER);
  await adminPage.fill('#user_pass', WP_PASS);
  await adminPage.click('#wp-submit');
  await adminPage.waitForURL(/wp-admin/);

  // Open settings page and enable cache
  await adminPage.goto(ADMIN_CACHE_URL);
  await adminPage.waitForSelector('#edp_pc_enabled');

  const isChecked = await adminPage.isChecked('#edp_pc_enabled');
  console.log('Cache enabled before save:', isChecked);

  if (!isChecked) {
    await adminPage.check('#edp_pc_enabled');
  }

  await adminPage.selectOption('select[name="edp_seo[page_cache][ttl]"]', '24');
  await adminPage.click('button[type=submit].edp-btn-primary');
  await adminPage.waitForURL(/updated=1/);

  // Confirm checkbox is still checked after save
  await adminPage.goto(ADMIN_CACHE_URL);
  const afterSave = await adminPage.isChecked('#edp_pc_enabled');
  console.log('Cache enabled after save:', afterSave);
  expect(afterSave).toBe(true);

  await adminCtx.close();
});

// ── 2. Guest visit populates cache ────────────────────────────────────────────
test('first guest visit stores cache; second visit returns HIT', async ({ request }) => {
  // First request — MISS, should populate
  const res1 = await request.get(`${WP_URL}${CITY_PATH}`, { maxRedirects: 5 });
  console.log('1st status:', res1.status());
  console.log('1st X-EDP-Cache:', res1.headers()['x-edp-cache'] ?? 'not set');
  expect(res1.status()).toBe(200);

  // Second request — should be HIT
  const res2 = await request.get(`${WP_URL}${CITY_PATH}`, { maxRedirects: 5 });
  console.log('2nd status:', res2.status());
  console.log('2nd X-EDP-Cache:', res2.headers()['x-edp-cache'] ?? 'not set');
  expect(res2.status()).toBe(200);
  expect(res2.headers()['x-edp-cache']).toBe('HIT');
});

// ── 3. Admin panel shows the cached page ─────────────────────────────────────
test('admin cached-pages table shows the cached city URL', async ({ browser }) => {
  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();

  await adminPage.goto(`${WP_URL}/wp-login.php`);
  await adminPage.fill('#user_login', WP_USER);
  await adminPage.fill('#user_pass', WP_PASS);
  await adminPage.click('#wp-submit');
  await adminPage.waitForURL(/wp-admin/);

  await adminPage.goto(ADMIN_CACHE_URL);

  const table = adminPage.locator('.edp-card table.widefat');
  if (await table.isVisible()) {
    const rows = await table.locator('tbody tr').count();
    console.log('Cached pages in table:', rows);
    const urlCell = table.locator('tbody tr:first-child td:first-child a');
    if (await urlCell.isVisible()) {
      console.log('First cached URL:', await urlCell.textContent());
    }
    expect(rows).toBeGreaterThan(0);
  } else {
    const emptyMsg = adminPage.locator('.edp-card-body p');
    console.log('Empty state message:', await emptyMsg.textContent());
    expect(table).toBeVisible(); // will fail with a clear message
  }

  await adminCtx.close();
});
