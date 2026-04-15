# Local SEO Dental Service Areas (WordPress plugin)

Virtual location SEO: thousands of state/city pages from a custom DB table (not `wp_posts`), plugin-owned templates, CSV import, and JSON-LD.

## URLs (after permalinks / activation)

- States index: `/locations/`
- Cities in a state: `/locations/{state_slug}/`
- City landing: `/locations/{state_slug}/{city_slug}/`

Flush permalinks: **Settings → Permalinks → Save** if routes 404 after deploy.

## Admin

- **Local SEO → Templates** — Global templates for `states_index`, `state_cities`, `city_landing` (meta title/description, H1, WYSIWYG). Variables documented on screen.
- **Local SEO → Import** — Upload a `.csv` from your computer, or leave the file field empty to use `raw_data.csv` on the server (if present). USA **50 states + DC** only; rows grouped by state + city; ZIPs merged. **Google Places:** save an API key (Google Cloud Console → Places API (New)), use **Test connection** to verify (1 search call), then run batched imports (default **emergency dentist**, max **5** per city). Each batch processes one city at a time via AJAX — no gateway timeouts. Optional: fetch opening hours + phone (extra API call per business). Optional constant: `EDP_GOOGLE_PLACES_API_KEY` in `wp-config.php`.
- **Local SEO → Locations** — Map a row to an existing Page/Post ID, create a hidden CPT override, or clear overrides. **Listings column:** shows count of stored businesses per city with inline Update (↻) and Delete (✕) buttons — no page reload required. Select rows + **Bulk actions → Fetch Google** for multiple cities.

## WP-CLI

```bash
wp edp-seo import
wp edp-seo import /absolute/path/to/raw_data.csv

wp edp-seo import-google --offset=0 --limit=50
wp edp-seo import-google --offset=50 --limit=50 --search-only
wp edp-seo test-google
```

## Branch flow & deploy

- `dev`: daily development.
- `main`: production deploy branch (GitHub Actions rsync on push).
- Merge `dev` → `main` and push when ready.

## Local development (Local App)

Symlink this repo into `wp-content/plugins/emergencydentalpros`, activate **Local SEO Dental Service Areas**, import CSV, then visit `/locations/`.

## Frontend build (Tailwind/Vite)

```bash
npm install
npm run build      # outputs to assets/
npm run dev        # watch mode
npm run lint:js    # ESLint
npm run format     # Prettier
```

Vite CSS/JS loads only on virtual SEO routes (`/locations/...`).

## PHP code quality (PHPCS + WordPress Coding Standards)

```bash
composer install
composer lint         # check for violations
composer lint:fix     # auto-fix where possible
```

Config: `phpcs.xml.dist`. Standards: WordPress, WordPress-Extra, WordPress-Docs.

## GitHub Actions secrets

`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `KNOWN_HOSTS` (recommended).

Remote path: `/var/www/widev.pro/public_html/wp-content/plugins/emergencydentalpros/`
