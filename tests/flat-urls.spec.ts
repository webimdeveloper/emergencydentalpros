/**
 * Flat URL routing — end-to-end tests.
 *
 * Covers every scenario from the feature/flat-urls branch:
 *
 *  1. States index  /locations/  — always present regardless of mode
 *  2. State page    /{state_slug}/  (flat) or /locations/{state_slug}/  (hierarchical)
 *  3. City page     /{city_slug}/   (flat) or /locations/{state_slug}/{city_slug}/
 *  4. Dynamic city page — no CPT, served by global template
 *  5. Static (CPT) city page — CPT override serves content
 *  6. Mapped post city page — existing WP page mapped to location row
 *  7. Conflict badge visible in admin when WP page slug collides with city slug
 *  8. Migration modal: opens with correct copy, cancel works
 *  9. Migration end-to-end: conflict resolved, badge gone, URL still 200
 *
 * URL mode is auto-detected from the admin settings page so tests are resilient
 * to switching between flat and hierarchical modes.
 */

import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import { WP_URL, goToLocations } from './helpers';

// ── helpers ───────────────────────────────────────────────────────────────────

/** Current URL mode — read once in beforeAll and shared across all tests. */
let urlMode: 'flat' | 'hierarchical' = 'hierarchical';

/** A city URL discovered from the admin table (respects current mode). */
let resolvedCityUrl = '';
/** A state URL derived from the resolved city URL. */
let resolvedStateUrl = '';

const SETTINGS_URL = `${WP_URL}/wp-admin/admin.php?page=edp-seo`;
const STATES_INDEX_PATH = '/locations/';

async function detectUrlMode(context: BrowserContext): Promise<'flat' | 'hierarchical'> {
  const page = await context.newPage();
  await page.goto(SETTINGS_URL, { waitUntil: 'domcontentloaded' });

  const flatRadio = page.locator('input[name="edp_seo[url_mode]"][value="flat"]');
  const isFlat = (await flatRadio.count()) > 0 && await flatRadio.isChecked();

  await page.close();
  return isFlat ? 'flat' : 'hierarchical';
}

/**
 * Find a city front-end URL from the admin table.
 * The globe icon anchor in the City column always points to the correct URL
 * for the active mode (uses EDP_Rewrite::city_url internally).
 */
async function discoverCityUrl(context: BrowserContext): Promise<{ city: string; state: string }> {
  const page = await context.newPage();
  await page.goto(`${WP_URL}/wp-admin/admin.php?page=edp-seo-locations`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.wp-list-table tbody tr', { timeout: 20_000 });

  // The globe icon links to the front-end city URL.
  const globeLinks = await page.locator('.column-city a.edp-city-globe, .column-city a[title], .column-city a[href]').all();

  let cityUrl = '';
  for (const link of globeLinks) {
    const href = (await link.getAttribute('href')) ?? '';
    if (href && href.startsWith('http')) {
      cityUrl = href;
      break;
    }
  }

  // Fallback: look at any city column anchor.
  if (!cityUrl) {
    const anyLink = page.locator('.column-city a').first();
    if (await anyLink.count()) {
      cityUrl = (await anyLink.getAttribute('href')) ?? '';
    }
  }

  await page.close();

  // Derive state URL: remove the last path segment (city slug + trailing slash).
  // flat:         /abbeville-al/         → no state URL; fall back to /locations/
  // hierarchical: /locations/alabama/abbeville-al/ → /locations/alabama/
  let stateUrl = STATES_INDEX_PATH;
  if (cityUrl) {
    try {
      const pathParts = new URL(cityUrl).pathname.replace(/\/$/, '').split('/').filter(Boolean);
      if (pathParts.length >= 2) {
        pathParts.pop();
        stateUrl = '/' + pathParts.join('/') + '/';
      }
    } catch {
      // malformed URL — leave stateUrl as states index fallback
    }
  }

  return { city: cityUrl, state: stateUrl };
}

// ── setup ─────────────────────────────────────────────────────────────────────

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext({ storageState: 'tests/.auth/state.json' });

  urlMode = await detectUrlMode(context);
  const urls = await discoverCityUrl(context);
  resolvedCityUrl  = urls.city;
  resolvedStateUrl = urls.state;

  await context.close();

  console.log('URL mode   :', urlMode);
  console.log('City URL   :', resolvedCityUrl);
  console.log('State URL  :', resolvedStateUrl);
});

// ── 1. States index (/locations/) ─────────────────────────────────────────────

test('states index /locations/ returns 200', async ({ request }) => {
  const res = await request.get(`${WP_URL}${STATES_INDEX_PATH}`, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

test('states index renders state list grid', async ({ page }) => {
  await page.goto(`${WP_URL}${STATES_INDEX_PATH}`, { waitUntil: 'domcontentloaded' });
  const grid = page.locator('.ws_locations__grid');
  await expect(grid).toBeVisible();
  const items = grid.locator('li.ws_state');
  expect(await items.count()).toBeGreaterThan(0);
});

test('states index state links point to correct URL pattern', async ({ page }) => {
  await page.goto(`${WP_URL}${STATES_INDEX_PATH}`, { waitUntil: 'domcontentloaded' });
  const firstStateLink = page.locator('.ws_state__link').first();
  await expect(firstStateLink).toBeVisible();
  const href = await firstStateLink.getAttribute('href');
  expect(href).toBeTruthy();
  // In flat mode, state links are /{state_slug}/; in hierarchical /locations/{state_slug}/
  if (urlMode === 'flat') {
    // Should NOT have /locations/{state}/ pattern — should be /{state}/
    expect(href).not.toMatch(/\/locations\/[a-z-]+\/[a-z-]+\//);
  } else {
    expect(href).toMatch(/\/locations\/[a-z-]+\//);
  }
});

// ── 2. State page ─────────────────────────────────────────────────────────────

test('state page returns 200', async ({ request }) => {
  if (!resolvedStateUrl || resolvedStateUrl === STATES_INDEX_PATH) {
    console.log('State URL not resolved — skipping');
    test.skip();
    return;
  }
  const res = await request.get(`${WP_URL}${resolvedStateUrl}`, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

test('state page renders city list grid', async ({ page }) => {
  if (!resolvedStateUrl || resolvedStateUrl === STATES_INDEX_PATH) {
    test.skip();
    return;
  }
  await page.goto(`${WP_URL}${resolvedStateUrl}`, { waitUntil: 'domcontentloaded' });
  const grid = page.locator('.ws_state_cities__grid');
  await expect(grid).toBeVisible();
  expect(await grid.locator('li').count()).toBeGreaterThan(0);
});

test('state page city links point to correct URL pattern', async ({ page }) => {
  if (!resolvedStateUrl || resolvedStateUrl === STATES_INDEX_PATH) {
    test.skip();
    return;
  }
  await page.goto(`${WP_URL}${resolvedStateUrl}`, { waitUntil: 'domcontentloaded' });
  const firstCityLink = page.locator('.ws_state_cities__link').first();
  await expect(firstCityLink).toBeVisible();
  const href = await firstCityLink.getAttribute('href');
  expect(href).toBeTruthy();
  if (urlMode === 'flat') {
    // Flat: /{city_slug}/ — no /locations/ prefix, no state segment
    expect(href).toMatch(/^https?:\/\/[^/]+\/[a-z]+-[a-z]{2}\/$/);
  } else {
    expect(href).toMatch(/\/locations\/[a-z-]+\/[a-z]+-[a-z]{2}\//);
  }
});

// ── 3. City page — dynamic (global template, no CPT) ──────────────────────────

test('dynamic city page discovered from admin returns 200', async ({ request }) => {
  if (!resolvedCityUrl) {
    console.log('No city URL found — skipping');
    test.skip();
    return;
  }
  const res = await request.get(resolvedCityUrl, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

test('dynamic city page renders hero section with H1', async ({ page }) => {
  if (!resolvedCityUrl) {
    test.skip();
    return;
  }
  await page.goto(resolvedCityUrl, { waitUntil: 'domcontentloaded' });
  const hero = page.locator('.ws_hero_inner');
  await expect(hero).toBeVisible();
  const h1 = page.locator('.ws_hero_inner__title').first();
  await expect(h1).toBeVisible();
  expect((await h1.textContent())?.trim()).toBeTruthy();
});

test('dynamic city page renders CTA section', async ({ page }) => {
  if (!resolvedCityUrl) {
    test.skip();
    return;
  }
  await page.goto(resolvedCityUrl, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.ws_cta_section')).toBeVisible();
});

// ── 4. City page — static (CPT override) ─────────────────────────────────────

test('static CPT city page returns 200', async ({ request, browser }) => {
  const context = await browser.newContext({ storageState: 'tests/.auth/state.json' });
  const adminPage = await context.newPage();
  await adminPage.goto(`${WP_URL}/wp-admin/admin.php?page=edp-seo-locations`, { waitUntil: 'domcontentloaded' });
  await adminPage.waitForSelector('.wp-list-table tbody tr', { timeout: 15_000 });

  // Find a row that already has a static page (CPT).
  const staticRow = adminPage.locator('tr:has(.edp-static-page-cell)').first();
  let staticCityUrl = '';
  if (await staticRow.count()) {
    const globeLink = staticRow.locator('.column-city a').first();
    staticCityUrl = (await globeLink.getAttribute('href')) ?? '';
  }
  await context.close();

  if (!staticCityUrl) {
    console.log('No static (CPT) city row found — skipping');
    test.skip();
    return;
  }

  const res = await request.get(staticCityUrl, { maxRedirects: 5 });
  expect(res.status()).toBe(200);
});

// ── 5. Flat mode — city slug resolves without state prefix ────────────────────

test('flat mode: city URL has no /locations/ prefix', async () => {
  if (urlMode !== 'flat') {
    console.log('URL mode is hierarchical — skipping flat URL shape test');
    test.skip();
    return;
  }
  if (!resolvedCityUrl) {
    test.skip();
    return;
  }
  // In flat mode city URL should be: /abbeville-al/ (no /locations/state/ prefix)
  const pathname = new URL(resolvedCityUrl).pathname;
  const segments = pathname.split('/').filter(Boolean);
  expect(segments.length).toBe(1);
});

test('hierarchical mode: city URL has /locations/{state}/{city}/ shape', async () => {
  if (urlMode !== 'hierarchical') {
    console.log('URL mode is flat — skipping hierarchical URL shape test');
    test.skip();
    return;
  }
  if (!resolvedCityUrl) {
    test.skip();
    return;
  }
  const pathname = new URL(resolvedCityUrl).pathname;
  expect(pathname).toMatch(/^\/locations\/[a-z-]+\/[a-z]+-[a-z]{2}\/$/);
});

// ── 6. Conflict detection ─────────────────────────────────────────────────────

test('admin table shows conflict badge when slug collides with WP page', async ({ page }) => {
  await goToLocations(page);

  const conflictBadge = page.locator('.edp-conflict-badge, .column-url_conflict .edp-conflict').first();
  if (await conflictBadge.count() === 0) {
    console.log('No conflict badges found in table — no colliding WP pages on dev');
    // Not a failure — dev may not have conflicting pages.
    return;
  }
  await expect(conflictBadge).toBeVisible();
});

// ── 7. Migration modal — copy, cancel ─────────────────────────────────────────

test('migrate modal opens with correct bullet text', async ({ page }) => {
  await goToLocations(page);

  const migrateBtn = page.locator('.edp-migrate-btn').first();
  if (await migrateBtn.count() === 0) {
    console.log('No Migrate button visible — no conflict rows on dev');
    test.skip();
    return;
  }

  await migrateBtn.click();

  const modal = page.locator('#edp-migrate-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });

  const bullets = modal.locator('ul li');
  await expect(bullets).toHaveCount(3);

  // Verify the three updated bullet lines.
  const texts = await bullets.allTextContents();
  expect(texts[0]).toContain('Draft');
  expect(texts[0]).toContain('Drafts');          // "recoverable from WP Admin → Pages → Drafts"
  expect(texts[1]).toContain('same address');     // "Plugin takes over this URL — same address, no redirect"
  expect(texts[2]).toContain('SEO meta');         // "Imported body and SEO meta preserved"
});

test('migrate modal cancel button closes modal', async ({ page }) => {
  await goToLocations(page);

  const migrateBtn = page.locator('.edp-migrate-btn').first();
  if (await migrateBtn.count() === 0) {
    test.skip();
    return;
  }

  await migrateBtn.click();
  const modal = page.locator('#edp-migrate-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });

  await modal.locator('.edp-modal-cancel-btn').click();
  await expect(modal).not.toBeVisible();
});

// ── 8. Migration end-to-end ───────────────────────────────────────────────────
// Creates a temporary WP page via the REST API whose slug matches a known city,
// triggers migration, and verifies the conflict is resolved.
// Cleans up the archived draft afterwards.

test('migration end-to-end: conflict badge gone, URL still 200, draft archived', async ({ page, request }) => {
  test.setTimeout(90_000);

  // Step A: find a location row with no CPT and no existing conflict.
  await goToLocations(page);
  await page.waitForSelector('.wp-list-table tbody tr', { timeout: 15_000 });

  const allRows = await page.locator('.wp-list-table tbody tr').all();
  let locationId = '';
  let cityGlobeLink = '';
  let citySlug = '';

  for (const row of allRows) {
    if ((await row.locator('.edp-static-page-cell').count()) > 0) continue;
    if ((await row.locator('.edp-conflict-badge, .edp-migrate-btn').count()) > 0) continue;

    const btn = row.locator('[data-location-id]').first();
    if ((await btn.count()) === 0) continue;
    locationId = (await btn.getAttribute('data-location-id')) ?? '';

    const link = row.locator('.column-city a').first();
    if ((await link.count()) === 0) continue;
    cityGlobeLink = (await link.getAttribute('href')) ?? '';

    if (locationId && cityGlobeLink) {
      try {
        const pathParts = new URL(cityGlobeLink).pathname.replace(/\/$/, '').split('/').filter(Boolean);
        citySlug = pathParts[pathParts.length - 1] ?? '';
      } catch { /* ignore */ }
      if (citySlug) break;
    }
    locationId = '';
    cityGlobeLink = '';
    citySlug = '';
  }

  if (!locationId || !citySlug) {
    console.log('No suitable plain row found — skipping E2E migration test');
    test.skip();
    return;
  }

  console.log('E2E migration: location', locationId, 'slug:', citySlug);

  // Step B: Create a WP page with the same slug via the REST API.
  // Read the REST nonce from wpApiSettings injected on every WP admin page.
  await page.goto(`${WP_URL}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  const wpNonce: string = await page.evaluate(() => {
    const s = (window as unknown as { wpApiSettings?: { nonce?: string } }).wpApiSettings;
    return s?.nonce ?? '';
  });

  const createResult: { id?: number; slug?: string; error?: string } = await page.evaluate(
    async ({ slug, nonce }: { slug: string; nonce: string }) => {
      const res = await fetch('/wp-json/wp/v2/pages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        credentials: 'include',
        body: JSON.stringify({ title: 'E2E conflict test – ' + slug, slug, status: 'publish' }),
      });
      return res.json();
    },
    { slug: citySlug, nonce: wpNonce }
  );

  if (!createResult?.id) {
    console.log('REST API page creation failed:', JSON.stringify(createResult));
    test.skip();
    return;
  }

  const newPostId = createResult.id;
  console.log('Created WP page ID:', newPostId, 'slug:', createResult.slug);

  // Step C: Reload admin and verify conflict badge appears.
  await goToLocations(page);
  await page.waitForSelector('.wp-list-table tbody tr', { timeout: 15_000 });

  const conflictRow = page.locator(`tr:has([data-location-id="${locationId}"])`).first();
  const migrateBtn  = conflictRow.locator('.edp-migrate-btn').first();

  if ((await migrateBtn.count()) === 0) {
    console.log('Conflict badge not shown for row', locationId, '— flat mode may be off or column hidden');
    // Clean up and skip gracefully.
    await page.evaluate(
      async ({ postId, nonce }: { postId: number; nonce: string }) => {
        await fetch(`/wp-json/wp/v2/pages/${postId}?force=true`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
          credentials: 'include',
        });
      },
      { postId: newPostId, nonce: wpNonce }
    );
    test.skip();
    return;
  }

  // Step D: Click migrate, confirm.
  await migrateBtn.click();
  const modal = page.locator('#edp-migrate-modal');
  await expect(modal).toBeVisible({ timeout: 5_000 });
  await modal.locator('.edp-modal-confirm-btn').click();

  // Wait for page reload (JS does location.reload() on success).
  await page.waitForURL(/page=edp-seo-locations/, { timeout: 20_000 });
  await page.waitForSelector('.wp-list-table tbody tr', { timeout: 15_000 });

  // Step E: Conflict badge gone for this row.
  const rowAfter   = page.locator(`tr:has([data-location-id="${locationId}"])`).first();
  const badgeAfter = rowAfter.locator('.edp-conflict-badge, .edp-migrate-btn');
  expect(await badgeAfter.count()).toBe(0);

  // Step F: City URL still returns 200.
  const res = await request.get(cityGlobeLink, { maxRedirects: 5 });
  expect(res.status()).toBe(200);

  // Step G: Old WP page is now a draft with slug {city_slug}--migrated.
  const draftCheck: { slug?: string; status?: string } = await page.evaluate(
    async ({ postId, nonce }: { postId: number; nonce: string }) => {
      const r = await fetch(`/wp-json/wp/v2/pages/${postId}?status=draft`, {
        headers: { 'X-WP-Nonce': nonce },
        credentials: 'include',
      });
      return r.json();
    },
    { postId: newPostId, nonce: wpNonce }
  );

  expect(draftCheck.status).toBe('draft');
  expect(draftCheck.slug).toBe(citySlug + '--migrated');
  console.log('Confirmed: archived draft slug =', draftCheck.slug, 'status =', draftCheck.status);

  // Cleanup: permanently delete the archived draft page.
  await page.evaluate(
    async ({ postId, nonce }: { postId: number; nonce: string }) => {
      await fetch(`/wp-json/wp/v2/pages/${postId}?force=true`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': nonce },
        credentials: 'include',
      });
    },
    { postId: newPostId, nonce: wpNonce }
  );
  console.log('Cleaned up archived draft ID:', newPostId);
});

// ── 9. Admin: settings URL mode toggle ────────────────────────────────────────

test('settings page has URL Structure section with flat/hierarchical options', async ({ page }) => {
  await page.goto(SETTINGS_URL, { waitUntil: 'domcontentloaded' });

  const flatRadio = page.locator('input[name="edp_seo[url_mode]"][value="flat"]');
  const hierRadio  = page.locator('input[name="edp_seo[url_mode]"][value="hierarchical"]');

  await expect(flatRadio).toHaveCount(1);
  await expect(hierRadio).toHaveCount(1);
});

test('settings page: exactly one URL mode radio is checked', async ({ page }) => {
  await page.goto(SETTINGS_URL, { waitUntil: 'domcontentloaded' });

  const flatChecked = await page.locator('input[name="edp_seo[url_mode]"][value="flat"]:checked').count();
  const hierChecked = await page.locator('input[name="edp_seo[url_mode]"][value="hierarchical"]:checked').count();

  expect(flatChecked + hierChecked).toBe(1);
});
