/**
 * Page parity — dynamic vs. static city page.
 *
 * Both page types go through the same theme city.php override, so their HTML
 * sections and CSS class signatures must be identical. This file verifies:
 *   1. Both pages return 200.
 *   2. The main wrapper has the same tag, id, and classes.
 *   3. Mandatory sections (hero, CTA) are present and class-identical on both.
 *   4. Conditional sections present on both share the same CSS classes.
 *   5. H1 and body text are non-empty on both pages.
 *
 * Selectors target the theme's city.php override (ws_* classes), not the
 * plugin's fallback template (edp-seo-* classes).
 */

import { test, expect, type Page } from '@playwright/test';
import { WP_URL } from './helpers';

// ── no auth — guest view only ─────────────────────────────────────────────────
test.use({ storageState: { cookies: [], origins: [] } });

const DYNAMIC_URL = `${WP_URL}/locations/massachusetts/chicopee-ma/`;
const STATIC_URL  = `${WP_URL}/locations/massachusetts/agawam-ma/`;

/**
 * Sections from the theme's city.php override.
 * "mandatory" = always rendered regardless of data.
 * "conditional" = rendered only when the corresponding $edp_data value is non-empty.
 */
const MANDATORY_SECTIONS = [
  { sel: '#main',           label: 'main wrapper' },
  { sel: '.ws_hero_inner',  label: 'hero section' },
  { sel: '.ws_cta_section', label: 'CTA section'  },
] as const;

const CONDITIONAL_SECTIONS = [
  '.ws_page_content',       // body text
  '.ws_zipcodes',           // ZIP codes
  '.ws_zipcodes__list',
  '.ws_businesses',         // nearby businesses
  '.ws_businesses__item--main',
  '.ws_nearby_cities',      // other cities in state
  '.ws_nearby_cities__list',
  '.edp-faq-city',          // FAQ
  '.ws_faq__list',
  '.ws_faq__item',
] as const;

async function getClass(page: Page, sel: string): Promise<string | null> {
  const loc = page.locator(sel).first();
  return (await loc.count()) > 0 ? await loc.getAttribute('class') : null;
}

// ── 1. Both pages return 200 ──────────────────────────────────────────────────
test('dynamic city page returns 200', async ({ request }) => {
  const res = await request.get(DYNAMIC_URL, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

test('static city page returns 200', async ({ request }) => {
  const res = await request.get(STATIC_URL, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

// ── 2. Mandatory sections: present and class-identical on both pages ───────────
test('mandatory sections are present on both pages with identical CSS classes', async ({ page }) => {
  await page.goto(DYNAMIC_URL, { waitUntil: 'domcontentloaded' });
  const dynClasses = Object.fromEntries(
    await Promise.all(MANDATORY_SECTIONS.map(async ({ sel, label }) => [sel, await getClass(page, sel)]))
  );

  await page.goto(STATIC_URL, { waitUntil: 'domcontentloaded' });
  const statClasses = Object.fromEntries(
    await Promise.all(MANDATORY_SECTIONS.map(async ({ sel, label }) => [sel, await getClass(page, sel)]))
  );

  for (const { sel, label } of MANDATORY_SECTIONS) {
    expect(dynClasses[sel],  `dynamic page missing ${label} (${sel})`).not.toBeNull();
    expect(statClasses[sel], `static page missing ${label} (${sel})`).not.toBeNull();
    expect(dynClasses[sel], `${label} class mismatch`).toBe(statClasses[sel]);
  }
});

// ── 3. Conditional sections: class-identical when both pages have them ─────────
test('conditional sections share identical CSS classes when present on both pages', async ({ page }) => {
  await page.goto(DYNAMIC_URL, { waitUntil: 'domcontentloaded' });
  const dynMap: Record<string, string | null> = {};
  for (const sel of CONDITIONAL_SECTIONS) dynMap[sel] = await getClass(page, sel);

  await page.goto(STATIC_URL, { waitUntil: 'domcontentloaded' });
  const statMap: Record<string, string | null> = {};
  for (const sel of CONDITIONAL_SECTIONS) statMap[sel] = await getClass(page, sel);

  const mismatches: string[] = [];
  const onlyDynamic: string[] = [];
  const onlyStatic:  string[] = [];

  for (const sel of CONDITIONAL_SECTIONS) {
    const d = dynMap[sel];
    const s = statMap[sel];

    if (d !== null && s !== null) {
      if (d !== s) mismatches.push(`${sel}\n  dynamic: "${d}"\n  static:  "${s}"`);
    } else if (d !== null) {
      onlyDynamic.push(sel);
    } else if (s !== null) {
      onlyStatic.push(sel);
    }
  }

  console.log('Sections only on dynamic page:', onlyDynamic.length ? onlyDynamic : 'none');
  console.log('Sections only on static page:',  onlyStatic.length  ? onlyStatic  : 'none');

  expect(
    mismatches,
    `CSS class mismatches found:\n${mismatches.join('\n\n')}`,
  ).toHaveLength(0);
});

// ── 4. H1 is non-empty on both pages ─────────────────────────────────────────
test('dynamic page renders a non-empty H1', async ({ page }) => {
  await page.goto(DYNAMIC_URL, { waitUntil: 'domcontentloaded' });
  const h1 = page.locator('.ws_hero_inner__title').first();
  await expect(h1).toBeVisible();
  expect((await h1.textContent())?.trim()).toBeTruthy();
});

test('static page renders a non-empty H1', async ({ page }) => {
  await page.goto(STATIC_URL, { waitUntil: 'domcontentloaded' });
  const h1 = page.locator('.ws_hero_inner__title').first();
  await expect(h1).toBeVisible();
  expect((await h1.textContent())?.trim()).toBeTruthy();
});

// ── 5. Body section is non-empty when present ─────────────────────────────────
test('dynamic page body section is non-empty when present', async ({ page }) => {
  await page.goto(DYNAMIC_URL, { waitUntil: 'domcontentloaded' });
  const section = page.locator('.ws_page_content').first();
  if ((await section.count()) === 0) {
    console.log('dynamic: .ws_page_content absent — body template is empty');
    return;
  }
  const prose = section.locator('.ws_entry_content').first();
  expect((await prose.innerHTML())?.trim()).toBeTruthy();
});

test('static page body section is non-empty when present', async ({ page }) => {
  await page.goto(STATIC_URL, { waitUntil: 'domcontentloaded' });
  const section = page.locator('.ws_page_content').first();
  if ((await section.count()) === 0) {
    console.log('static: .ws_page_content absent — body is empty (expected for unconfigured static pages)');
    return;
  }
  const prose = section.locator('.ws_entry_content').first();
  expect((await prose.innerHTML())?.trim()).toBeTruthy();
});

// ── 6. FAQ section class-identical when both pages have it ────────────────────
test('FAQ section has identical CSS classes on both pages when present', async ({ page }) => {
  await page.goto(DYNAMIC_URL, { waitUntil: 'domcontentloaded' });
  const dynFaq = await getClass(page, '.edp-faq-city');

  await page.goto(STATIC_URL, { waitUntil: 'domcontentloaded' });
  const statFaq = await getClass(page, '.edp-faq-city');

  console.log(`FAQ present — dynamic: ${dynFaq !== null}, static: ${statFaq !== null}`);

  if (dynFaq !== null && statFaq !== null) {
    expect(dynFaq).toBe(statFaq);
  }
});
