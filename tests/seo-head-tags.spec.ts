/**
 * SEO head tags — canonical, OG, FAQPage + BreadcrumbList schema, and settings UI.
 */

import { test, expect } from '@playwright/test';
import { WP_URL } from './helpers';

const CITY_PATH = '/locations/alabama/abbeville-al/';
const SETTINGS_URL = `${WP_URL}/wp-admin/admin.php?page=edp-seo`;

// ── 1. Canonical tag ──────────────────────────────────────────────────────────

test('city page outputs a canonical link tag', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const canonical = page.locator('link[rel="canonical"]');
  await expect(canonical).toHaveCount(1);

  const href = await canonical.getAttribute('href');
  expect(href).toContain(CITY_PATH.replace(/\/$/, ''));
});

// ── 2. OG tags ────────────────────────────────────────────────────────────────

test('city page outputs og:type meta tag', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const ogType = page.locator('meta[property="og:type"]');
  await expect(ogType).toHaveCount(1);
  await expect(ogType).toHaveAttribute('content', 'website');
});

test('city page outputs og:title meta tag', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const ogTitle = page.locator('meta[property="og:title"]');
  await expect(ogTitle).toHaveCount(1);
  const content = await ogTitle.getAttribute('content');
  expect(content).toBeTruthy();
  expect(content!.length).toBeGreaterThan(0);
});

test('city page outputs og:description meta tag', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const ogDesc = page.locator('meta[property="og:description"]');
  await expect(ogDesc).toHaveCount(1);
  const content = await ogDesc.getAttribute('content');
  expect(content).toBeTruthy();
});

test('city page outputs og:url meta tag matching canonical', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const ogUrl = page.locator('meta[property="og:url"]');
  await expect(ogUrl).toHaveCount(1);
  const content = await ogUrl.getAttribute('content');
  expect(content).toContain(CITY_PATH.replace(/\/$/, ''));
});

// ── 3. BreadcrumbList schema ──────────────────────────────────────────────────

test('city page outputs BreadcrumbList JSON-LD with 4 items', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const schemas = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
    return scripts.map(s => {
      try { return JSON.parse(s.textContent ?? ''); } catch { return null; }
    }).filter(Boolean);
  });

  const breadcrumb = schemas.find((s: any) => s['@type'] === 'BreadcrumbList');
  expect(breadcrumb).toBeTruthy();
  expect(breadcrumb.itemListElement).toHaveLength(4);
  expect(breadcrumb.itemListElement[0].name).toBe('Home');
  expect(breadcrumb.itemListElement[1].name).toBe('Locations');
  // Position 3 = state, 4 = city
  expect(breadcrumb.itemListElement[2].position).toBe(3);
  expect(breadcrumb.itemListElement[3].position).toBe(4);
});

// ── 4. Dentist schema still present ──────────────────────────────────────────

test('city page still outputs Dentist JSON-LD', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  const schemas = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
    return scripts.map(s => {
      try { return JSON.parse(s.textContent ?? ''); } catch { return null; }
    }).filter(Boolean);
  });

  const dentist = schemas.find((s: any) => s['@type'] === 'Dentist');
  expect(dentist).toBeTruthy();
  expect(dentist.areaServed).toBeTruthy();
});

// ── 5. FAQPage schema (when FAQ is enabled) ───────────────────────────────────

test('city page outputs FAQPage JSON-LD when FAQ section is present', async ({ page }) => {
  await page.goto(`${WP_URL}${CITY_PATH}`);

  // If the FAQ section is in the DOM, the JSON-LD must be there too.
  const faqSection = page.locator('.edp-faq-city');
  const hasFaq = await faqSection.count();

  if (hasFaq === 0) {
    // FAQ disabled for this city — skip schema check.
    return;
  }

  const schemas = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
    return scripts.map(s => {
      try { return JSON.parse(s.textContent ?? ''); } catch { return null; }
    }).filter(Boolean);
  });

  const faqSchema = schemas.find((s: any) => s['@type'] === 'FAQPage');
  expect(faqSchema).toBeTruthy();
  expect(Array.isArray(faqSchema.mainEntity)).toBe(true);
  expect(faqSchema.mainEntity.length).toBeGreaterThan(0);
  expect(faqSchema.mainEntity[0]['@type']).toBe('Question');
});

// ── 6. Admin settings — Social & OG card is visible ──────────────────────────

test('settings page shows Social & Open Graph card', async ({ page }) => {
  await page.goto(SETTINGS_URL);

  await expect(page.locator('text=Social & Open Graph')).toBeVisible();
  await expect(page.locator('#edp_og_image_url')).toBeVisible();
  await expect(page.locator('#edp_twitter_site')).toBeVisible();
});

// ── 7. Admin settings — OG image URL saves and persists ──────────────────────

test('OG image URL field saves and reloads correctly', async ({ page }) => {
  await page.goto(SETTINGS_URL);

  const testUrl = 'https://example.com/og-test.jpg';
  const input = page.locator('#edp_og_image_url');

  await input.fill(testUrl);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();

  // Wait for the success notice (redirect back to settings page).
  await expect(page.locator('.edp-notice-success')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('#edp_og_image_url')).toHaveValue(testUrl);

  // Clean up — clear the field.
  await page.locator('#edp_og_image_url').fill('');
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await expect(page.locator('.edp-notice-success')).toBeVisible({ timeout: 15_000 });
});
