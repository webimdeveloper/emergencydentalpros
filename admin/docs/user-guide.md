# Local SEO Plugin — User Guide

A practical reference for admins managing location pages, importing data, connecting APIs, and publishing city landing pages.

---

## 1. Admin Menu Structure

The plugin adds a **Local SEO** menu to the WordPress admin sidebar with four sections:

| Page | URL | Purpose |
|------|-----|---------|
| **Templates** | `?page=edp-seo` | Global SEO settings and page templates |
| **Locations** | `?page=edp-seo-locations` | Location table — manage, filter, create pages |
| **Settings** | `?page=edp-seo-import` | Google Sheets sync, Service Account, Google Places API |
| **Documentation** | `?page=edp-seo-doc` | Hidden page — linked from admin cards only |

---

## 2. Importing Location Data

The plugin uses a CSV file as its primary data source. Each row represents one city.

### Required CSV columns

| Column | Description |
|--------|-------------|
| `zip` | Primary ZIP code |
| `city` | City display name |
| `state_id` | Two-letter state abbreviation (e.g. `TX`) |
| `state_name` | Full state name |

Optional columns: `state_slug`, `city_slug`, `county_name`, `zips` (comma-separated list of all ZIPs).

### Steps

1. Go to **Local SEO → Settings**.
2. Upload your `raw_data.csv` file or place it at the server path shown on screen.
3. Click **Run Import**.
4. Rows are matched by `state_id + city_slug` — re-importing is safe, existing rows are updated.

> The importer only processes US rows (50 states + DC). Rows are grouped by state and city; ZIP codes are merged per group.

---

## 3. Google Sheets — Two-Way Sync

The two-way sync reads location data from a Google Sheet, upserts rows into the database, then writes `city_slug`, `sync_note`, and `last_synced` back to the sheet.

### Setup flow

1. **Create a Service Account** in Google Cloud Console → IAM & Admin → Service Accounts → Keys → Add Key → JSON.
2. **Upload the JSON key** at **Local SEO → Settings → Google Sheets — Service Account**.
3. **Share your Sheet** with the service account email as **Editor**.
4. **Save the Sheet URL** in the Two-Way Sync card.
5. Click **Run Two-Way Sync**.

### Required sheet columns (row 1)

```
action, status, type, google_places, faq, city, state_id, state_name,
county_name, main_zip, zips, city_slug, sync_note, last_synced
```

Only rows where `action = TRUE` are processed. After sync, `action` is reset to `FALSE`.

---

## 4. Google Places API

Fetches nearby dental business listings for each city page.

### Setup

1. Go to **Local SEO → Settings → Google Places — Settings**.
2. Paste your Google Cloud API key (requires **Places API** enabled).
3. Set search term (default: `emergency dentist`) and max businesses per city (max 5).
4. Optionally enable **Fetch full hours + phone** (one extra API call per business).
5. Save.

### Fetching data

- **Single location:** In the Locations table, click the Google icon in a row's Google column.
- **Bulk:** Select rows → **Bulk Actions → Fetch Google**.

---

## 5. Managing the Locations Table

The Locations table lists every city row. Key columns:

| Column | Description |
|--------|-------------|
| **State** | State name and abbreviation |
| **City** | City name — click the globe icon to preview the live page |
| **Google** | Number of nearby business listings fetched |
| **Override** | Static page or mapped post status |
| **SEO** | PageSpeed score — click to run, hover for breakdown |
| **CQS** | Content Quality Score — click Analyze, hover for breakdown |
| **Actions** | Create static page, map post ID, delete row |

### Stat card filter toggles

The left stat card above the table shows three coverage counters. Each is a clickable filter toggle:

| Toggle | Filters to… |
|--------|------------|
| **Static pages** | Rows with a dedicated CPT page created |
| **Mapped post IDs** | Rows linked to an existing WordPress post |
| **Custom FAQ** | Static pages with a custom FAQ section enabled |

Click once to activate — the table filters and scrolls automatically. Click again to clear. An info banner appears at the top showing active filters with a **Clear filter** link.

### Column filters

Click the **funnel icon** on the State or City column header to filter by value.

### Bulk actions

| Action | Description |
|--------|-------------|
| Fetch Google | Pull Google Places data for selected cities |
| Analyze Content | Compute CQS score for selected cities |
| Delete Rows | Permanently remove selected rows and their data |

### Delete All Rows

The **Delete All Rows** button in the stat card removes every location row and all associated Google data. This cannot be undone.

---

## 6. Creating Static Pages

A **Static Page** is a WordPress CPT post (`edp_seo_city`) that overrides the dynamic template for one specific city.

### Create

1. In the Locations table, find the row.
2. Click **+ Create** in the Actions column.
3. The plugin creates the CPT post with template defaults as starting content.

### Edit

Open the static page via the link in the Override column. Two metaboxes are available:

**Location Page Settings:**
- Meta title, Meta description
- H1 heading
- Communities H2 and body text (rich text editor)
- Other cities H2

**FAQ Section:**
- Enable/disable the FAQ block
- Override H2 heading and intro text
- Add, reorder, and delete Q&A items

Leave any field blank to fall back to the global template value.

### Remove

Click the **×** icon in the Override column to unlink the CPT post from the location row. The CPT post itself is moved to Trash separately.

---

## 7. Mapping a Post ID

Map any existing WordPress post as the content source for a city instead of creating a new CPT page.

1. In the Locations table, find the row.
2. In the **Actions** column, type a post ID in the **Map Post** input.
3. Press **Enter** — saved via AJAX immediately.
4. To clear, click the **×** button.

> CPT overrides take precedence over mapped posts. Mapped posts take precedence over the global template.

---

## 8. Global Templates

Templates define default content for all city pages without a static page override.

1. Go to **Local SEO → Templates**.
2. The page has three contexts, each with its own tab:

| Tab | Page type |
|-----|-----------|
| **States Index** | `/locations/` |
| **State + Cities** | `/locations/{state}/` |
| **City Landing** | `/locations/{state}/{city}/` |

3. Each context has: Meta title, Meta description, H1, Subtitle, and Content (rich text).
4. Use tokens to insert dynamic values:

| Token | Replaced with |
|-------|--------------|
| `{city_name}` | City name |
| `{state_name}` | Full state name |
| `{state_short}` | State abbreviation |
| `{main_zip}` | Primary ZIP code |
| `{list_of_related_zips}` | All ZIPs for this city |
| `{site_name}` | WordPress site name |
| `{county_name}` | County name |

5. Global **Settings** (business name, OG image URL, Twitter handle) are in the card to the right of the tabs.

---

## 9. FAQ Section

Each city page can show an FAQ block with FAQPage JSON-LD structured data for Google rich results.

### Per-page FAQ (static pages only)

In the static page's **FAQ Section** metabox:
- Toggle the section on/off with the **Enable FAQ section** checkbox.
- Override the H2 heading and intro text.
- Add Q&A items with the **+ Add item** button; drag to reorder.

---

## 10. SEO & Meta Tags

On each city page the plugin outputs:

| Tag | Source |
|-----|--------|
| `<title>` | CPT override → mapped post → template default |
| `<meta name="description">` | Same priority chain |
| `<link rel="canonical">` | Auto-generated from city slug |
| Open Graph (`og:title`, `og:description`, `og:image`) | Same chain + OG image from Settings |
| Twitter Card | Same chain + Twitter handle from Settings |

---

## 11. Schema Markup

Every city page automatically outputs JSON-LD structured data:

| Schema | Trigger |
|--------|---------|
| **Dentist** (LocalBusiness) | Always — uses business name + address from Settings |
| **BreadcrumbList** | Always — 4 levels: Home › Locations › {State} › {City} |
| **FAQPage** | When FAQ section is enabled and has at least one item |

---

## 12. Content Quality Score (CQS)

CQS is a 0–100 score measuring how completely a city page is set up.

### Run a score

- **Single:** Click **Analyze** in the CQS column.
- **Bulk:** Select rows → **Bulk Actions → Analyze Content**.
- Hover the score pill to see a category-by-category breakdown.

### Scoring categories

| Category | Max pts | What earns points |
|----------|---------|-------------------|
| Title & Meta | 20 | Meta title override, meta description override |
| Unique Content | 25 | Has static page (+10), communities body override (+15) |
| Heading Structure | 15 | H1, communities H2, other cities H2 |
| FAQ Quality | 15 | Enabled, has items, 5+ items |
| Local Business Data | 15 | Google count ≥ 1, Google count ≥ 5 |
| Schema | 10 | FAQPage active, BreadcrumbList (static page) |

Dynamic (template-only) pages are capped at **85**. Static pages can reach **100**.

### Score grades

| Grade | Score |
|-------|-------|
| Perfect | 100 |
| Great | 85–99 |
| Good | 70–84 |
| Average | 50–69 |
| Poor | 0–49 |

---

## 13. PageSpeed Insights

Click **Check SEO** in the SEO column to run a Google PageSpeed Insights audit for that city's live URL. Scores are cached and displayed as a coloured pill. Hover for the full LCP/CLS/FID breakdown.

Requires the Google Places API key to be saved (it uses the same key).
