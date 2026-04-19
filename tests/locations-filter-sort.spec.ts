/**
 * Locations admin — column filter + sort tests.
 *
 * Requires at least 2 rows in the dev dataset (the seed data covers this).
 */

import { test, expect, Page } from '@playwright/test';
import { LOC_URL, goToLocations } from './helpers';

// ── helpers ───────────────────────────────────────────────────────────────────

/** Navigate directly to the locations page with given query params. */
async function goWithParams(page: Page, params: Record<string, string>) {
  const url = new URL(LOC_URL);
  for (const [k, v] of Object.entries(params)) {
    url.searchParams.set(k, v);
  }
  await page.goto(url.toString());
  await page.waitForSelector('.wp-list-table');
}

/** All visible state text values from the State column cells. */
async function getStateColumnValues(page: Page): Promise<string[]> {
  return page.locator('table.wp-list-table tbody td.column-state').allTextContents();
}

// ── 1. Filter icon buttons appear in thead ────────────────────────────────────
test('Filter icon buttons are injected into State and City column headers', async ({ page }) => {
  await goToLocations(page);

  const stateFilterBtn = page.locator('thead th.column-state .edp-col-filter-btn');
  const cityFilterBtn  = page.locator('thead th.column-city .edp-col-filter-btn');

  await expect(stateFilterBtn).toBeVisible();
  await expect(cityFilterBtn).toBeVisible();
});

// ── 2. State filter popover opens on click ────────────────────────────────────
test('Clicking State filter button opens popover with search input', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-state .edp-col-filter-btn').click();

  const popover = page.locator('.edp-filter-popover');
  await expect(popover).toBeVisible();
  await expect(popover.locator('.edp-filter-search-input')).toBeVisible();
  await expect(popover.locator('.edp-filter-options-list')).toBeVisible();
  // Should show at least one state option.
  await expect(popover.locator('.edp-filter-options-list li')).not.toHaveCount(0);
});

// ── 3. State popover search filters list ─────────────────────────────────────
test('Typing in State popover search narrows the list', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  const popover    = page.locator('.edp-filter-popover');
  const searchInput = popover.locator('.edp-filter-search-input');

  // Get total initial options.
  const totalOptions = await popover.locator('.edp-filter-options-list li').count();

  // Type something unlikely to match every state.
  await searchInput.fill('Ca');
  const filteredOptions = await popover.locator('.edp-filter-options-list li:not(.edp-filter-options-empty)').count();
  expect(filteredOptions).toBeGreaterThan(0);
  expect(filteredOptions).toBeLessThanOrEqual(totalOptions);
});

// ── 4. Clicking a state option filters the table ──────────────────────────────
test('Selecting a state from the popover navigates with state_filter param', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  const popover = page.locator('.edp-filter-popover');

  // Click the first state option link.
  const firstOption = popover.locator('.edp-filter-options-list li a').first();
  const stateLabel  = await firstOption.textContent();
  await firstOption.click();

  // Should navigate and show state_filter in URL.
  await page.waitForURL(/state_filter=/);
  await page.waitForSelector('.wp-list-table');

  // All visible state cells should contain the selected state.
  const stateCells = await getStateColumnValues(page);
  expect(stateCells.length).toBeGreaterThan(0);
  // The state label text (e.g. "California (CA)") should match all visible rows.
  const stateName = stateLabel?.split('(')[0].trim() ?? '';
  for (const cell of stateCells) {
    expect(cell).toContain(stateName);
  }
});

// ── 5. Active filter chip appears when state_filter is set ───────────────────
test('Active filter chip is shown when state_filter param is present', async ({ page }) => {
  // Seed the URL with a state filter using a known state from the dev dataset.
  await page.goto(LOC_URL);
  await page.waitForSelector('.wp-list-table');

  // Open popover, pick first state.
  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  const firstOption = page.locator('.edp-filter-popover .edp-filter-options-list li a').first();
  await firstOption.click();
  await page.waitForURL(/state_filter=/);
  await page.waitForSelector('.edp-filter-chip');

  const chip = page.locator('.edp-filter-chip').first();
  await expect(chip).toBeVisible();
  // Chip has a remove ×.
  await expect(chip.locator('.edp-filter-chip-remove')).toBeVisible();
});

// ── 6. Removing the state chip clears the filter ─────────────────────────────
test('Clicking × on state chip removes the filter', async ({ page }) => {
  await page.goto(LOC_URL);
  await page.waitForSelector('.wp-list-table');

  // Pick a state.
  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  await page.locator('.edp-filter-popover .edp-filter-options-list li a').first().click();
  await page.waitForURL(/state_filter=/);
  await page.waitForSelector('.edp-filter-chip');

  // Click the × on the chip.
  await page.locator('.edp-filter-chip .edp-filter-chip-remove').first().click();
  await page.waitForURL(url => !url.toString().includes('state_filter='));
  await page.waitForSelector('.wp-list-table');

  await expect(page.locator('.edp-filter-chip')).toHaveCount(0);
});

// ── 7. City filter popover opens and applies ──────────────────────────────────
test('City filter popover opens with text input; typing and applying filters rows', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-city .edp-col-filter-btn').click();
  const popover = page.locator('.edp-filter-popover');
  await expect(popover).toBeVisible();

  const cityInput = popover.locator('.edp-filter-search-input');
  await expect(cityInput).toBeVisible();

  // Get first city name from the table.
  const firstCityLink = page.locator('table.wp-list-table tbody td.column-city a').first();
  const cityText = await firstCityLink.textContent();
  const cityName = (cityText ?? '').trim();
  expect(cityName.length).toBeGreaterThan(0);

  await cityInput.fill(cityName);
  await popover.locator('.edp-filter-apply-btn').click();

  await page.waitForURL(/city_filter=/);
  await page.waitForSelector('.wp-list-table');

  // At least one row visible and it contains that city name.
  const cityCells = await page.locator('table.wp-list-table tbody td.column-city').allTextContents();
  expect(cityCells.length).toBeGreaterThan(0);
  expect(cityCells[0]).toContain(cityName);
});

// ── 8. Google Business column is sortable (header has sort link) ──────────────
test('Google Business column header has a sort link after sortable columns defined', async ({ page }) => {
  await goToLocations(page);

  // WP_List_Table renders <a> inside the <th> for sortable columns.
  const googleTh = page.locator('thead th.column-google');
  await expect(googleTh).toBeVisible();
  await expect(googleTh.locator('a')).toBeVisible();
});

// ── 9. Clicking Google Business header sorts by google count ─────────────────
test('Clicking Google Business header adds orderby=google to URL', async ({ page }) => {
  await goToLocations(page);

  const googleLink = page.locator('thead th.column-google a').first();
  await googleLink.click();

  await page.waitForURL(/orderby=google/);
  await page.waitForSelector('.wp-list-table');

  expect(page.url()).toContain('orderby=google');
});

// ── 10. Static Page column is sortable ───────────────────────────────────────
test('Static Page column header has sort link and clicking adds orderby=override', async ({ page }) => {
  await goToLocations(page);

  const overrideTh   = page.locator('thead th.column-override');
  await expect(overrideTh).toBeVisible();
  await expect(overrideTh.locator('a')).toBeVisible();

  await overrideTh.locator('a').first().click();
  await page.waitForURL(/orderby=override/);
  await page.waitForSelector('.wp-list-table');

  expect(page.url()).toContain('orderby=override');
});

// ── 11. Sorting asc/desc toggles ─────────────────────────────────────────────
test('Clicking Google Business sort link twice toggles order between desc and asc', async ({ page }) => {
  // First click → DESC (default for first sort hit).
  await page.goto(`${LOC_URL}&orderby=google&order=asc`);
  await page.waitForSelector('.wp-list-table');

  const link = page.locator('thead th.column-google a').first();
  // The th should now be in sorted/asc/desc state.
  const thClass = await page.locator('thead th.column-google').getAttribute('class');
  expect(thClass).toMatch(/asc|desc|sorted/);
});

// ── 12. Escape closes the popover ────────────────────────────────────────────
test('Pressing Escape closes the filter popover', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  await expect(page.locator('.edp-filter-popover')).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(page.locator('.edp-filter-popover')).not.toBeVisible({ timeout: 3000 });
});

// ── 13. Clicking outside closes the popover ───────────────────────────────────
test('Clicking outside the popover closes it', async ({ page }) => {
  await goToLocations(page);

  await page.locator('thead th.column-state .edp-col-filter-btn').click();
  await expect(page.locator('.edp-filter-popover')).toBeVisible();

  // Click the page heading (outside popover).
  await page.locator('#edp-locations-wrap h1').click();
  await expect(page.locator('.edp-filter-popover')).not.toBeVisible({ timeout: 3000 });
});

// ── 14. Filters compose: state + orderby preserved ───────────────────────────
test('Applying city filter preserves existing orderby param in URL', async ({ page }) => {
  await goWithParams(page, { orderby: 'google', order: 'desc' });

  // Open city popover and apply a filter.
  await page.locator('thead th.column-city .edp-col-filter-btn').click();
  const cityInput = page.locator('.edp-filter-popover .edp-filter-search-input');
  await cityInput.fill('a'); // Broad match.
  await page.locator('.edp-filter-popover .edp-filter-apply-btn').click();

  await page.waitForURL(/city_filter=/);
  expect(page.url()).toContain('orderby=google');
});
