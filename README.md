# Local SEO Dental Service Areas (WordPress plugin)

Virtual location SEO: thousands of state/city pages from a custom DB table (not `wp_posts`), plugin-owned templates, CSV import, and JSON-LD.

## URLs (after permalinks / activation)

- States index: `/locations/`
- Cities in a state: `/locations/{state_slug}/`
- City landing: `/locations/{state_slug}/{city_slug}/`

Flush permalinks: **Settings → Permalinks → Save** if routes 404 after deploy.

## Admin

- **Local SEO → Templates** — Global templates for `states_index`, `state_cities`, `city_landing` (meta title/description, H1, WYSIWYG). Variables documented on screen.
- **Local SEO → Import** — Imports `raw_data.csv` from the plugin directory (or a custom absolute path). USA **50 states + DC** only; rows grouped by state + city; ZIPs merged.
- **Local SEO → Locations** — Map a row to an existing Page/Post ID, create a hidden CPT override, or clear overrides.

## WP-CLI

```bash
wp edp-seo import
wp edp-seo import /absolute/path/to/raw_data.csv
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
