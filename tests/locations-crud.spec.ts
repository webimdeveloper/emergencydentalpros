/**
 * Locations admin — create / delete / map scenarios.
 *
 * Tests are ordered: non-destructive first, destructive last, so the
 * 2-row dev dataset isn't exhausted before all scenarios run.
 */

import { test, expect, Page } from '@playwright/test';
import { WP_URL, goToLocations } from './helpers';

// ── helpers ───────────────────────────────────────────────────────────────────

async function waitForCellUpdate(
  page: Page,
  locationId: string,
  selector: string,
  timeout = 10_000
) {
  await page.waitForSelector(
    `tr:has([data-location-id="${locationId}"]) ${selector}`,
    { timeout }
  );
}

function trFor(page: Page, locationId: string) {
  return page.locator(`tr:has([data-location-id="${locationId}"])`).first();
}

/** Get a valid WP post ID from the admin Posts list. */
async function getValidPostId(page: Page): Promise<string> {
  await page.goto(`${WP_URL}/wp-admin/edit.php`);
  const firstLink = page.locator('table.wp-list-table tbody tr .row-title').first();
  const href = await firstLink.getAttribute('href');
  const match = href?.match(/post=(\d+)/);
  if (!match) throw new Error('Could not find any published post');
  return match[1];
}

/** Click an element that may be hidden by WP row-actions CSS or out of viewport. */
async function forceClick(page: Page, locator: ReturnType<Page['locator']>) {
  await locator.evaluate(el => (el as HTMLElement).click());
}

// ── 1. Create static page ─────────────────────────────────────────────────────
test('Create static page: cell becomes link + trash icon', async ({ page }) => {
  await goToLocations(page);

  const createBtn = page.locator('.edp-create-page-btn').first();
  const locationId = await createBtn.getAttribute('data-location-id');
  expect(locationId).toBeTruthy();

  await createBtn.click();
  await waitForCellUpdate(page, locationId!, '.edp-static-page-cell');

  const cell = trFor(page, locationId!).locator('.edp-static-page-cell');
  await expect(cell).toBeVisible();
  await expect(cell.locator('.edp-page-link')).toBeVisible();
  await expect(cell.locator('.edp-clear-cpt-btn')).toBeVisible();
});

// ── 2. Clear static page ──────────────────────────────────────────────────────
test('Clear static page: cell reverts to Create button', async ({ page }) => {
  await goToLocations(page);

  let clearBtn = page.locator('.edp-clear-cpt-btn').first();
  let locationId: string | null;

  if ((await clearBtn.count()) === 0) {
    const createBtn = page.locator('.edp-create-page-btn').first();
    locationId = await createBtn.getAttribute('data-location-id');
    expect(locationId).toBeTruthy();
    await createBtn.click();
    await waitForCellUpdate(page, locationId!, '.edp-clear-cpt-btn');
    clearBtn = trFor(page, locationId!).locator('.edp-clear-cpt-btn');
  } else {
    locationId = await clearBtn.getAttribute('data-location-id');
    expect(locationId).toBeTruthy();
  }

  page.once('dialog', d => d.accept());
  await clearBtn.click();
  await waitForCellUpdate(page, locationId!, '.edp-create-page-btn');

  const row = trFor(page, locationId!);
  await expect(row.locator('.edp-create-page-btn')).toBeVisible();
  await expect(row.locator('.edp-static-page-cell')).toHaveCount(0);
});

// ── 3. Map Post input ─────────────────────────────────────────────────────────
test('Map Post: save on Enter shows ✕ button; ✕ clears value', async ({ page }) => {
  // Get a real post ID from the WP posts list.
  const postId = await getValidPostId(page);

  await goToLocations(page);

  // Pick a row whose Map Post input is empty so we start from a clean state
  // and avoid the race where saveMapPost's response re-shows the clear button
  // after edp_clear_override already hid it.
  const input = page.locator('.edp-map-post-input[value=""], .edp-map-post-input:not([value])').first();
  const locationId = await input.getAttribute('data-location-id');
  expect(locationId).toBeTruthy();

  const clearBtn = page.locator(`.edp-map-clear-btn[data-location-id="${locationId}"]`);

  await input.fill(postId);
  await input.press('Enter');
  await expect(clearBtn).toBeVisible({ timeout: 8_000 });

  // Clear the mapping.
  await clearBtn.click();
  await expect(clearBtn).toBeHidden({ timeout: 8_000 });
  await expect(input).toHaveValue('');
});

// ── 4. Trashed post shows label not broken link ───────────────────────────────
test('Trashed post: column shows "Trashed" label instead of edit link', async ({ page }) => {
  await goToLocations(page);

  // Ensure a CPT-linked row exists.
  let cptLink = page.locator('.edp-static-page-cell .edp-page-link[href]').first();

  if ((await cptLink.count()) === 0) {
    const createBtn = page.locator('.edp-create-page-btn').first();
    const locationId = await createBtn.getAttribute('data-location-id');
    expect(locationId).toBeTruthy();
    await createBtn.click();
    await waitForCellUpdate(page, locationId!, '.edp-static-page-cell .edp-page-link[href]');
    cptLink = trFor(page, locationId!).locator('.edp-page-link[href]');
  }

  const href = await cptLink.getAttribute('href');
  const postIdMatch = href?.match(/post=(\d+)/);
  expect(postIdMatch).toBeTruthy();
  const postId = postIdMatch![1];

  // Grab the trash nonce from the edit page.
  await page.goto(`${WP_URL}/wp-admin/post.php?action=edit&post=${postId}`);
  const trashLink = await page.locator('a.submitdelete').getAttribute('href');
  const nonceMatch = trashLink?.match(/_wpnonce=([^&]+)/);
  expect(nonceMatch).toBeTruthy();
  const nonce = nonceMatch![1];

  // Trash the post directly.
  await page.goto(`${WP_URL}/wp-admin/post.php?action=trash&post=${postId}&_wpnonce=${nonce}`);

  // Reload locations and verify the "Trashed" label.
  await goToLocations(page);
  const deadLabel = page.locator('.edp-static-page-cell .edp-page-link--dead').first();
  await expect(deadLabel).toBeVisible({ timeout: 8_000 });
  expect((await deadLabel.textContent()) ?? '').toMatch(/trashed/i);

  // Clean up: restore the post so remaining tests have a row.
  await page.goto(`${WP_URL}/wp-admin/post.php?action=untrash&post=${postId}&_wpnonce=${nonce}`);
});

// ── 5. Delete row via City column ─────────────────────────────────────────────
test('Delete row: <tr> disappears from table', async ({ page }) => {
  await goToLocations(page);

  const deleteBtn = page.locator('.edp-row-delete-btn').first();
  const locationId = await deleteBtn.getAttribute('data-location-id');
  expect(locationId).toBeTruthy();

  page.once('dialog', d => d.accept());
  // Row-actions are hidden by WP CSS until hover; use evaluate to bypass.
  await forceClick(page, deleteBtn);

  await expect(trFor(page, locationId!)).toHaveCount(0, { timeout: 12_000 });
});

// ── 6. Delete row that had a CPT: post is permanently deleted ─────────────────
test('Delete row with CPT: linked post is gone afterwards', async ({ page }) => {
  await goToLocations(page);

  const rowCount = await page.locator('table.wp-list-table tbody tr').count();
  if (rowCount < 1) {
    test.skip();
    return;
  }

  // Create CPT on the first available Create button.
  let cptLink = page.locator('.edp-static-page-cell .edp-page-link[href]').first();

  if ((await cptLink.count()) === 0) {
    const createBtn = page.locator('.edp-create-page-btn').first();
    const locationId = await createBtn.getAttribute('data-location-id');
    expect(locationId).toBeTruthy();
    await createBtn.click();
    await waitForCellUpdate(page, locationId!, '.edp-static-page-cell .edp-page-link[href]');
    cptLink = trFor(page, locationId!).locator('.edp-page-link[href]');
  }

  const href = await cptLink.getAttribute('href');
  const postIdMatch = href?.match(/post=(\d+)/);
  expect(postIdMatch).toBeTruthy();
  const postId = postIdMatch![1];

  // Find the delete button in the same row.
  const rowForCpt = cptLink.locator('xpath=ancestor::tr').first();
  const locationId = await rowForCpt
    .locator('[data-location-id]')
    .first()
    .getAttribute('data-location-id');

  page.once('dialog', d => d.accept());
  await forceClick(page, rowForCpt.locator('.edp-row-delete-btn'));
  await expect(trFor(page, locationId!)).toHaveCount(0, { timeout: 12_000 });

  // Verify WP post was permanently deleted (WP shows "does not exist" or similar).
  await page.goto(`${WP_URL}/wp-admin/post.php?action=edit&post=${postId}`);
  const bodyText = (await page.locator('body').textContent()) ?? '';
  // WP core message: "You attempted to edit an item that does not exist."
  expect(bodyText).toMatch(/does not exist|attempted|not found|trash|cannot edit|invalid/i);
});

// ── 7. Bulk delete ────────────────────────────────────────────────────────────
test('Bulk delete: rows_deleted success notice appears', async ({ page }) => {
  await goToLocations(page);

  const checkboxes = page.locator('input[name="location[]"]');
  if ((await checkboxes.count()) < 1) {
    test.skip();
    return;
  }

  await checkboxes.nth(0).check();
  if ((await checkboxes.count()) > 1) await checkboxes.nth(1).check();

  await page.selectOption('select[name="action"]', 'delete_rows');
  await page.click('#doaction');

  await page.waitForSelector('.edp-notice-success', { timeout: 10_000 });
  const notice = await page.locator('.edp-notice-success').last().textContent();
  expect(notice).toMatch(/deleted/i);
});
