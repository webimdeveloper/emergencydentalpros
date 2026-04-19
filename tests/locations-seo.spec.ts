/**
 * Locations admin — SEO column (PageSpeed Insights) tests.
 *
 * These tests verify UI behaviour only — they do NOT call the real PageSpeed API.
 * The "Check SEO" AJAX call will fail on the dev server if the API key does not
 * have PageSpeed Insights enabled, so we mock the AJAX response where needed.
 */

import { test, expect, Page } from '@playwright/test';
import { goToLocations } from './helpers';

// ── helpers ───────────────────────────────────────────────────────────────────

/** Intercept the edp_check_pagespeed AJAX call and return a fake success response. */
async function mockPsiSuccess(page: Page, mobileScore = 87, desktopScore = 95) {
  await page.route('**/admin-ajax.php', async (route, request) => {
    const body = request.postData() ?? '';
    if (body.includes('action=edp_check_pagespeed')) {
      // Build the HTML the server would return.
      const status = mobileScore >= 90 ? 'ok' : mobileScore >= 50 ? 'attention' : 'crucial';
      const mMetrics = JSON.stringify({ lcp: '2.4 s', tbt: '120 ms', cls: '0.04', fcp: '1.1 s', si: '2.0 s' });
      const dMetrics = JSON.stringify({ lcp: '1.0 s', tbt: '10 ms',  cls: '0.01', fcp: '0.7 s', si: '1.1 s' });
      const html =
        `<div class="edp-seo-cell">` +
        `<div class="edp-seo-indicator edp-seo--${status}" ` +
          `data-location-id="1" ` +
          `data-mobile-score="${mobileScore}" ` +
          `data-desktop-score="${desktopScore}" ` +
          `data-mobile-metrics='${mMetrics}' ` +
          `data-desktop-metrics='${dMetrics}' ` +
          `data-checked-at="just now">` +
          `<span class="edp-seo-dot"></span>` +
          `<span class="edp-seo-score">${mobileScore}</span>` +
        `</div>` +
        `<button type="button" class="edp-recheck-seo-btn" data-location-id="1" data-nonce="fake">` +
          `<span class="dashicons dashicons-update"></span>` +
        `</button>` +
        `</div>`;

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: { html, mobile_score: mobileScore, desktop_score: desktopScore } }),
      });
    } else {
      await route.continue();
    }
  });
}

/** Intercept and return an API error. */
async function mockPsiError(page: Page, message = 'API key missing or invalid.') {
  await page.route('**/admin-ajax.php', async (route, request) => {
    const body = request.postData() ?? '';
    if (body.includes('action=edp_check_pagespeed')) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, data: { message } }),
      });
    } else {
      await route.continue();
    }
  });
}

// ── 1. SEO column header exists ───────────────────────────────────────────────
test('SEO column header is rendered in the locations table', async ({ page }) => {
  await goToLocations(page);

  const seoTh = page.locator('thead th.column-seo');
  await expect(seoTh).toBeVisible();
  await expect(seoTh).toContainText('SEO');
});

// ── 2. Check SEO button appears in each row ───────────────────────────────────
test('"Check SEO" button is present in each row that has no cached score', async ({ page }) => {
  await goToLocations(page);

  // At least one row should have the check button (fresh dev dataset has no cached scores).
  const checkBtns = page.locator('.edp-check-seo-btn');
  const count = await checkBtns.count();
  expect(count).toBeGreaterThan(0);

  // Button contains "Check SEO" text.
  await expect(checkBtns.first()).toContainText('Check SEO');
});

// ── 3. Click Check SEO → shows indicator (mocked) ────────────────────────────
test('Clicking "Check SEO" shows the status indicator on success', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 87, 95);

  const btn = page.locator('.edp-check-seo-btn').first();
  const td  = btn.locator('xpath=ancestor::td').first();

  await btn.click();

  // Wait for the indicator to appear.
  const indicator = td.locator('.edp-seo-indicator');
  await expect(indicator).toBeVisible({ timeout: 15_000 });

  // Score is shown.
  await expect(td.locator('.edp-seo-score')).toContainText('87');
});

// ── 4. Correct status class applied ──────────────────────────────────────────
test('Status class is "attention" when mobile score is 50–89', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 72, 91);

  await page.locator('.edp-check-seo-btn').first().click();
  const indicator = page.locator('.edp-seo-indicator').first();
  await expect(indicator).toBeVisible({ timeout: 15_000 });
  await expect(indicator).toHaveClass(/edp-seo--attention/);
});

test('Status class is "ok" when mobile score ≥ 90', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 94, 98);

  await page.locator('.edp-check-seo-btn').first().click();
  const indicator = page.locator('.edp-seo-indicator').first();
  await expect(indicator).toBeVisible({ timeout: 15_000 });
  await expect(indicator).toHaveClass(/edp-seo--ok/);
});

test('Status class is "crucial" when mobile score < 50', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 32, 61);

  await page.locator('.edp-check-seo-btn').first().click();
  const indicator = page.locator('.edp-seo-indicator').first();
  await expect(indicator).toBeVisible({ timeout: 15_000 });
  await expect(indicator).toHaveClass(/edp-seo--crucial/);
});

// ── 5. Recheck button appears after first check ───────────────────────────────
test('Recheck button appears next to indicator after successful check', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 87, 95);

  await page.locator('.edp-check-seo-btn').first().click();

  const recheckBtn = page.locator('.edp-recheck-seo-btn').first();
  await expect(recheckBtn).toBeVisible({ timeout: 15_000 });
});

// ── 6. Hover shows popover with metrics ──────────────────────────────────────
test('Hovering the status indicator shows the SEO popover', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 87, 95);

  await page.locator('.edp-check-seo-btn').first().click();

  const indicator = page.locator('.edp-seo-indicator').first();
  await expect(indicator).toBeVisible({ timeout: 15_000 });

  await indicator.hover();

  const popover = page.locator('.edp-seo-popover');
  await expect(popover).toBeVisible({ timeout: 3_000 });
  // Popover contains metric labels.
  await expect(popover).toContainText('LCP');
  await expect(popover).toContainText('CLS');
});

// ── 7. Mobile / Desktop tabs switch score ────────────────────────────────────
test('Clicking Desktop tab in popover shows desktop score', async ({ page }) => {
  await goToLocations(page);
  await mockPsiSuccess(page, 87, 95);

  await page.locator('.edp-check-seo-btn').first().click();

  const indicator = page.locator('.edp-seo-indicator').first();
  await expect(indicator).toBeVisible({ timeout: 15_000 });
  await indicator.hover();

  const popover = page.locator('.edp-seo-popover');
  await expect(popover).toBeVisible();

  // Click Desktop tab.
  const desktopTab = popover.locator('.edp-seo-tab[data-tab="desktop"]');
  await desktopTab.click();

  // Score big should show 95.
  await expect(popover.locator('.edp-seo-score-big')).toContainText('95');
});

// ── 8. Error case — button restored, alert shown ──────────────────────────────
test('API error restores the Check SEO button (no status indicator left)', async ({ page }) => {
  await goToLocations(page);
  await mockPsiError(page, 'API key missing or invalid.');

  // Intercept the alert dialog.
  let alertMsg = '';
  page.once('dialog', async d => { alertMsg = d.message(); await d.accept(); });

  const btn = page.locator('.edp-check-seo-btn').first();
  await btn.click();

  // Dialog should have appeared.
  await page.waitForTimeout(2_000);
  expect(alertMsg).toContain('API key');

  // Button should be restored (no indicator).
  await expect(page.locator('.edp-seo-indicator').first()).not.toBeVisible();
  await expect(page.locator('.edp-check-seo-btn').first()).toBeVisible();
});

// ── 9. Recheck button triggers a new check ───────────────────────────────────
test('Clicking recheck button fires another pagespeed check and updates the cell', async ({ page }) => {
  await goToLocations(page);

  // First check returns 72.
  await mockPsiSuccess(page, 72, 88);
  await page.locator('.edp-check-seo-btn').first().click();
  await expect(page.locator('.edp-seo-indicator').first()).toBeVisible({ timeout: 15_000 });

  // Remove first mock, install one returning 91.
  await page.unrouteAll();
  await mockPsiSuccess(page, 91, 97);

  const recheckBtn = page.locator('.edp-recheck-seo-btn').first();
  await recheckBtn.click();

  // Score should update to 91.
  await expect(page.locator('.edp-seo-score').first()).toContainText('91', { timeout: 15_000 });
  await expect(page.locator('.edp-seo-indicator').first()).toHaveClass(/edp-seo--ok/);
});
