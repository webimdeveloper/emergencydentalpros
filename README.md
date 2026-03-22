# Local SEO Dental Service Areas (WordPress plugin)

Virtual location SEO: thousands of state/city pages from a custom DB table (not `wp_posts`), plugin-owned templates, CSV import, and JSON-LD.

## URLs (after permalinks / activation)

- States index: `/locations/`
- Cities in a state: `/locations/{state_slug}/`
- City landing: `/locations/{state_slug}/{city_slug}/`

Flush permalinks: **Settings → Permalinks → Save** if routes 404 after deploy.

## Admin

- **Local SEO → Templates** — Global templates for `states_index`, `state_cities`, `city_landing` (meta title/description, H1, WYSIWYG). Variables documented on screen.
- **Local SEO → Import** — Upload a `.csv` from your computer, or leave the file field empty to use `raw_data.csv` on the server (if present). USA **50 states + DC** only; rows grouped by state + city; ZIPs merged. **Yelp:** same screen — save a [Yelp Fusion](https://www.yelp.com/developers/documentation/v3) **API Key** (used as Bearer token; Client ID is optional metadata only), use **Test Yelp API connection** to verify the key (1 search call), then run batched imports (default **Dentists**, max **10** per city). Optional: fetch opening hours (extra API call per listing). Follow Yelp’s [display requirements](https://www.yelp.com/developers/display_requirements). For large sites, stay within monthly quotas (use small batches / `--search-only` in CLI to reduce calls). Optional constant: `EDP_YELP_API_KEY` in `wp-config.php` instead of storing the key in the database.
- **Local SEO → Locations** — Map a row to an existing Page/Post ID, create a hidden CPT override, or clear overrides. **Yelp column:** green dot when listings are stored for that city; **Fetch Yelp** on a row to import/refresh only that city. Select rows + **Bulk actions → Fetch Yelp** for multiple cities.

## WP-CLI

```bash
wp edp-seo import
wp edp-seo import /absolute/path/to/raw_data.csv

wp edp-seo import-yelp --offset=0 --limit=50
wp edp-seo import-yelp --offset=50 --limit=50 --search-only
wp edp-seo test-yelp
```

## Branch flow & deploy

- `dev`: daily development.
- `main`: production deploy branch (GitHub Actions rsync on push).
- Merge `dev` → `main` and push when ready.

## Local development (Local App)

Symlink this repo into `wp-content/plugins/emergencydentalpros`, activate **Local SEO Dental Service Areas**, import CSV, then visit `/locations/`.

## Frontend build (optional Tailwind/Vite)

```bash
npm install
npm run build   # outputs to assets/
npm run dev
```

Vite CSS/JS loads only on virtual SEO routes (`/locations/...`).

## GitHub Actions secrets

`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `KNOWN_HOSTS` (recommended).

Remote path: `/var/www/widev.pro/public_html/wp-content/plugins/emergencydentalpros/`
