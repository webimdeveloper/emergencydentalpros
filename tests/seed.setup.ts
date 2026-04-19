/**
 * Data-seed setup: ensures the locations table has rows before tests run.
 * Calls the dev-only `edp_dev_seed_csv` AJAX endpoint (requires WP_DEBUG=true).
 * The nonce is embedded in the locations page's `nonces.seedCsv` variable.
 */
import { test as setup } from '@playwright/test';
import { WP_URL, goToLocations } from './helpers';

const MIN_ROWS = 4;

setup('seed location data', async ({ page }) => {
  await goToLocations(page);

  const rows = await page.locator('table.wp-list-table tbody tr').count();

  if (rows >= MIN_ROWS) {
    console.log(`Seed: ${rows} rows present — no seeding needed.`);
    return;
  }

  console.log(`Seed: only ${rows} rows — importing from raw_data.csv…`);

  // Read the dev nonce injected as window.edpDevSeedNonce (only present when WP_DEBUG=true).
  const seedNonce: string | null = await page.evaluate(() => {
    return (window as unknown as { edpDevSeedNonce?: string }).edpDevSeedNonce ?? null;
  });

  if (!seedNonce) {
    console.log('Seed: no seedCsv nonce (WP_DEBUG may be off) — tests may skip if no data.');
    return;
  }

  const ajaxUrl: string = await page.evaluate(
    () => (window as unknown as { ajaxurl: string }).ajaxurl
  );

  const result: { success: boolean; data: { rows?: number; error?: string } } =
    await page.evaluate(
      async ({ url, nonce }: { url: string; nonce: string }) => {
        const res = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'edp_dev_seed_csv', nonce }).toString(),
          credentials: 'include',
        });
        return res.json();
      },
      { url: ajaxUrl, nonce: seedNonce }
    );

  if (!result?.success) {
    console.log(`Seed: CSV import failed — ${result?.data?.error ?? JSON.stringify(result)}`);
    console.log('Tests that require data will skip.');
    return;
  }

  console.log(`Seed: imported ${result.data.rows ?? '?'} rows from CSV.`);

  // Reload page to confirm rows are visible.
  await goToLocations(page);
  const afterRows = await page.locator('table.wp-list-table tbody tr').count();
  console.log(`Seed: ${afterRows} rows now in table.`);
});
