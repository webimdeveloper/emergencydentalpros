# Local SEO Plugin — User Guide

A practical reference for admins managing location pages, importing data, connecting APIs, and publishing city landing pages.

---

## 1. Importing Location Data

The plugin uses a CSV file (`raw_data.csv`) as its primary data source. Each row represents one city/location combination.

### Required columns
| Column | Description |
|--------|-------------|
| `state_id` | Two-letter state abbreviation (e.g. `TX`) |
| `state_name` | Full state name |
| `state_slug` | URL-safe version (e.g. `texas`) |
| `city_name` | City display name |
| `city_slug` | URL-safe version (e.g. `austin`) |
| `zips` | Comma-separated ZIP codes served |

### Steps to import

1. Go to **Local SEO → Settings** in the WordPress admin menu.
2. Under **CSV Import**, either:
   - Upload `raw_data.csv` directly, or
   - Place the file at the server path shown on screen (default: `wp-content/plugins/emergencydentalpros/raw_data.csv`)
3. Click **Run Import**.
4. The importer will add new rows, skip duplicates, and report results.

> **Note:** Re-importing is safe — existing rows are matched by `state_id + city_slug` and updated, not duplicated.

---

## 2. Connecting External APIs

### Google Places API

Used to fetch nearby dental businesses for each city page.

1. Obtain a Google Cloud API key with **Places API** enabled.
2. Go to **Local SEO → Settings → Google Business**.
3. Paste the key and click Save.
4. Test connectivity with the **Test API** button.
5. To fetch data for a city, go to **Locations**, select rows, and use **Bulk Actions → Fetch Google**.

### PageSpeed Insights (SEO column)

Uses the same Google API key — no separate setup required. Click **Check SEO** in any row's SEO column to run a PageSpeed Insights audit for that city page.

---

## 3. Managing the Locations Table

The **Locations** table lists every city row in the database. Key columns:

| Column | What it shows |
|--------|---------------|
| **State** | State name and abbreviation |
| **City** | City name — click the globe icon to preview the live page |
| **Google Business** | Number of nearby business listings fetched |
| **Static Page** | Whether a per-city CPT post exists; create one with the **+** button |
| **SEO** | PageSpeed score (click to run, hover to see breakdown) |
| **CQS** | Content Quality Score (click Analyze, hover for breakdown) |
| **Map Post** | Enter a post ID to map any existing WordPress post as this city's content source |

### Filtering and sorting

- Click the **funnel icon** on State or City headers to filter by value.
- Click **Google Business** or **Static Page** column headers to sort.

### Bulk actions

Select rows with the checkboxes, then choose an action:

| Action | Description |
|--------|-------------|
| Fetch Google | Pull Google Places data for selected cities |
| Create Pages | Create static CPT pages for selected cities |
| Analyze Content | Compute CQS score for selected cities |
| Delete Rows | Permanently remove selected rows and their data |

---

## 4. Creating Static Pages

A **Static Page** is a WordPress CPT post (`edp_seo_city`) that overrides the dynamic template for one specific city.

### Create a single page

1. In the Locations table, find the city row.
2. Click the **+ Create** button in the **Static Page** column.
3. The plugin auto-creates the CPT post with template defaults as the content.

### Edit a static page

1. Click the **post ID link** in the Static Page column to open the WP editor.
2. Use the **Location Page Settings** metabox to override:
   - Meta title
   - Meta description
   - H1 heading
   - Communities section H2 and body text
   - Other cities section H2
3. Use the **FAQ Section** metabox to enable/disable the FAQ block and add Q&A items.
4. Leave any field blank to inherit from the global City Landing template.

### Remove a static page override

Click the **trash icon** in the Static Page column. This clears the CPT link from the location row (the CPT post itself is trashed separately).

---

## 5. Mapping a Post ID

If you have an existing WordPress post (any type) that should serve as the content for a city, you can map it instead of creating a new CPT page.

1. Find the row in the Locations table.
2. In the **Map Post** column, type the WordPress post ID.
3. Press **Enter** or click away — the mapping is saved via AJAX immediately.
4. To clear the mapping, click the **×** button that appears.

> When a post is mapped, the plugin reads its content, title, and meta for that city URL. CPT overrides take precedence over mapped posts.

---

## 6. Global Templates

Templates control the default content for all city pages that don't have a static page override.

1. Go to **Local SEO → Templates**.
2. Edit the **City Landing** template fields:
   - Meta title, Meta description, H1, Communities H2, Communities body, Other cities H2
3. Use template tokens to insert dynamic values:

| Token | Replaced with |
|-------|--------------|
| `{city}` | City name |
| `{state}` | State name |
| `{state_abbr}` | State abbreviation |
| `{zip}` | Primary ZIP code |

4. Changes apply to all dynamic city pages immediately (no cache to clear).

---

## 7. FAQ Section

Each city page can show an FAQ block with structured data (FAQPage JSON-LD for Google rich results).

### Global FAQ template

Set default FAQ items in **Local SEO → Templates → FAQ**.

### Per-page FAQ override

In a static page's **FAQ Section** metabox:
- Toggle the section on/off
- Override the H2 heading and intro text
- Add, reorder, or delete Q&A items
- Leave blank to inherit the global FAQ template

---

## 8. SEO & Meta Tags

The plugin outputs:
- `<title>` — from meta title override, or template default
- `<meta name="description">` — from meta description override, or template default
- Canonical URL — auto-generated from the city's slug
- Open Graph tags (`og:title`, `og:description`, `og:image`)
- Twitter Card tags

Configure OG image URL and Twitter handle in **Local SEO → Settings → SEO**.

---

## 9. Schema Markup

Every city page automatically outputs:

- **Dentist / LocalBusiness** JSON-LD — business name, address, phone from Settings
- **BreadcrumbList** JSON-LD — 4-level breadcrumb (Home › Locations › State › City)
- **FAQPage** JSON-LD — when FAQ section is enabled and has items

---

## 10. Content Quality Score (CQS)

CQS is a 0–100 score that measures how completely a city page has been set up.

Click **Analyze** in the CQS column, or use **Bulk Actions → Analyze Content**.

Hover the score pill to see a category-by-category breakdown. Aim for **85+** for competitive results. Static pages with fully filled overrides can reach **100**.
