# Local SEO Plugin — Architecture Reference

Technical overview of how the plugin is structured, how requests are routed, how classes interact, and how to extend it.

---

## 1. Plugin Bootstrap (`emergencydentalpros.php`)

The main plugin file:
- Defines constants: `EDP_PLUGIN_VERSION`, `EDP_PLUGIN_DIR`, `EDP_PLUGIN_URL`
- `require_once`s all class files in dependency order
- Registers `activation_hook` → `EDP_Activator::activate()`
- On `plugins_loaded`: calls `EDP_Database::ensure_schema()`, then registers all subsystems

**Load order:**
```
EDP_Database → EDP_Settings → EDP_Activator → EDP_Template_Engine → EDP_Schema
→ EDP_CPT → EDP_Content_Resolver → EDP_Cqs_Scorer → EDP_View_Controller → EDP_Admin
```

Google Sheets classes loaded separately: `EDP_Sheet_Credentials` → `EDP_Sheet_API` → `EDP_Sheet_Fetcher` → `EDP_Sheet_Sync`

---

## 2. Database Layer (`class-edp-database.php`)

All direct `$wpdb` queries go through this static class. No other class queries the database directly.

### Tables

| Table | Purpose |
|-------|---------|
| `{prefix}seo_locations` | Master location rows (state, city, slugs, ZIPs, override info) |
| `{prefix}seo_nearby_businesses` | Google Places results per location |
| `{prefix}seo_pagespeed_cache` | PageSpeed Insights scores + metrics |
| `{prefix}edp_cqs_cache` | Content Quality Scores + breakdown JSON |

### Schema versioning

`EDP_Activator::DB_VERSION` (currently `1.5.0`) is stored in `wp_options`. On every `plugins_loaded`, `EDP_Database::ensure_schema()` compares the stored version with the constant and calls `EDP_Activator::create_tables()` via `dbDelta` if they differ. No destructive migrations — `dbDelta` only adds columns/tables, never removes.

### Key static methods

| Method | Description |
|--------|-------------|
| `count_rows()` | Total location rows |
| `count_static_pages()` | Rows with `override_type = 'cpt'` |
| `count_mapped_posts()` | Rows with `override_type = 'mapped'` |
| `count_with_custom_faq()` | Rows whose CPT post has `_edp_faq_enabled = 1` |
| `get_cqs_for_locations(array $ids)` | Bulk fetch CQS cache for a page of rows |
| `upsert_cqs_cache(int $id, int $score, array $breakdown)` | Save CQS result |

---

## 3. Virtual Routing (`class-edp-rewrite.php`)

The plugin creates virtual pages without adding rows to `wp_posts`.

### Registered rewrite rules

| URL pattern | Query vars |
|-------------|-----------|
| `locations/` | `edp_location_index=1` |
| `locations/{state}/` | `edp_state_slug={state}` |
| `locations/{state}/{city}/` | `edp_state_slug={state}&edp_city_slug={city}` |

Rules are added via `add_rewrite_rule()` in `EDP_Rewrite::register()` (hooked to `init`). Flush permalinks after plugin activation.

### Request interception

`EDP_View_Controller` hooks into `template_redirect`. When it detects a plugin query var:
1. Calls `EDP_View_Controller::build_view_data()` to resolve all content
2. Loads the appropriate template: `city.php`, `state.php`, or `index.php`
3. Calls `exit` — WordPress never loads its own template

**Theme override:** The view controller checks `get_stylesheet_directory() . '/emergencydentalpros/views/{template}.php'` first. Theme files receive the same `$data` array as plugin defaults.

---

## 4. Content Resolution (`class-edp-content-resolver.php`)

City page content is resolved through a three-level priority chain:

```
CPT post meta override
    ↓ (if field is empty)
Mapped post content
    ↓ (if no mapped post)
Global City Landing template
```

`EDP_Content_Resolver::resolve_city(array $row): array` returns a flat array of all resolved fields:
`meta_title`, `meta_description`, `h1`, `communities_h2`, `communities_body`, `other_cities_h2`.

Results are cached per-request in a static `$cache` array keyed by `location_id`.

---

## 5. Template Engine (`class-edp-template-engine.php`)

Handles `{token}` replacement in template strings.

### Key methods

| Method | Description |
|--------|-------------|
| `base_vars(): array` | Global tokens from settings (business name, phone, site name) |
| `context_from_city_row(array $base, array $row): array` | Merges city tokens onto base |
| `replace(string $template, array $vars): string` | Performs `{token}` substitution |

### Available tokens

`{city_name}`, `{state_name}`, `{state_short}`, `{state_slug}`, `{county_name}`, `{main_zip}`, `{list_of_related_zips}`, `{site_name}`

---

## 6. SEO Head Output (`class-edp-view-controller.php`)

On city pages, the following hooks fire in `wp_head`:

| Priority | Method | Output |
|----------|--------|--------|
| 1 | `output_canonical` | `<link rel="canonical">` |
| 2 | `output_og_tags` | Open Graph + Twitter Card meta |
| 5 | `output_meta_title` | `<title>` |
| 6 | `output_meta_description` | `<meta name="description">` |
| 10 | `output_city_schema` | Dentist + BreadcrumbList JSON-LD |
| 99 | `output_faqpage_schema` | FAQPage JSON-LD (when FAQ enabled) |

---

## 7. Schema Output (`class-edp-schema.php`)

All structured data (JSON-LD) is generated here.

### `output_city_schema(array $row): void`
Two schemas in one `<script type="application/ld+json">` block:
1. **Dentist** (extends LocalBusiness) — name, address, phone, URL from plugin settings
2. **BreadcrumbList** — 4 items: Home › Locations › {State} › {City}

### `output_faqpage_schema(array $faq_items): void`
Outputs **FAQPage** JSON-LD from the FAQ items array.

---

## 8. CPT — Static Page Overrides (`class-edp-cpt.php`)

Post type: `edp_seo_city` (not publicly queryable, shown in WP admin)

Each CPT post is linked to a location row via `_edp_location_id` post meta.

### Metaboxes

| Metabox | Meta keys |
|---------|-----------|
| Location Page Settings | `_edp_meta_title`, `_edp_meta_description`, `_edp_h1`, `_edp_communities_h2`, `_edp_communities_body`, `_edp_other_cities_h2` |
| FAQ Section | `_edp_faq_enabled`, `_edp_faq_h2`, `_edp_faq_intro`, `_edp_faq_items` (JSON array) |

Communities body uses `wp_editor()` (teeny mode). All other text fields use `sanitize_text_field`. Rich text uses `wp_kses_post`.

---

## 9. Content Quality Score (`class-edp-cqs-scorer.php`)

Stateless scorer: `EDP_Cqs_Scorer::compute(int $location_id, array $row): array`

### Scoring categories (100 pts total)

| Category | Max | Key signals |
|----------|-----|-------------|
| Title & Meta | 20 | meta_title override, meta_description override |
| Unique Content | 25 | has CPT (+10), communities_body override (+15) |
| Heading Structure | 15 | H1, communities H2, other cities H2 |
| FAQ Quality | 15 | enabled, has items, 5+ items |
| Local Business Data | 15 | google_count ≥ 1, google_count ≥ 5 |
| Schema | 10 | FAQPage active, BreadcrumbList (static page) |

Dynamic (template-only) pages cap at **85**. Static pages can reach **100**.

### Grade thresholds

`perfect` (100) → `great` (85+) → `good` (70+) → `average` (50+) → `poor`

Results stored in `{prefix}edp_cqs_cache` and displayed in the Locations table CQS column.

---

## 10. Google Sheets Sync

Four classes handle the two-way sync pipeline:

| Class | File | Responsibility |
|-------|------|----------------|
| `EDP_Sheet_Credentials` | `class-edp-sheet-credentials.php` | Store/retrieve service account JSON in `wp_options` (encrypted at rest) |
| `EDP_Sheet_API` | `class-edp-sheet-api.php` | Low-level HTTP calls to the Sheets API using the service account JWT |
| `EDP_Sheet_Fetcher` | `class-edp-sheet-fetcher.php` | Read rows from the sheet, parse into location arrays |
| `EDP_Sheet_Sync` | `class-edp-sheet-sync.php` | Orchestrate: fetch → upsert DB → write back slug/notes/timestamp |

The sync is triggered via `wp_ajax_edp_sheet_sync_v2`. The AJAX handler in `EDP_Admin::ajax_sheet_sync_v2()` runs it synchronously and returns a JSON result with rows processed and any per-row messages.

---

## 11. Google Places (`class-edp-google-places-client.php`)

`EDP_Google_Places_Client` makes Places API requests. `EDP_Google_Places_Importer` orchestrates the fetch + DB write per location.

Config stored in `wp_options` via `EDP_Google_Places_Config`: `api_key`, `term`, `limit`, `fetch_details`.

Nearby businesses are stored in `{prefix}seo_nearby_businesses` and displayed on city pages via the `$data['nearby_businesses']` array.

---

## 12. Admin Layer (`admin/class-edp-admin.php`)

Central class for all WP admin functionality.

### Menu structure

| Slug | Label | Handler |
|------|-------|---------|
| `edp-seo` | Templates | `render_settings()` |
| `edp-seo-locations` | Locations | `render_locations()` |
| `edp-seo-import` | Settings | `render_import()` |
| `edp-seo-doc` | *(hidden)* | `render_doc()` |

### Registered AJAX actions

| Action | Handler | Description |
|--------|---------|-------------|
| `edp_google_import_step` | `ajax_google_import_step` | Incremental Google Places import |
| `edp_google_fetch_location` | `ajax_google_fetch_location` | Fetch one location |
| `edp_google_delete_location` | `ajax_google_delete_location` | Delete one location's Google data |
| `edp_sheet_sync_v2` | `ajax_sheet_sync_v2` | Google Sheets two-way sync |
| `edp_save_post_mapping` | `ajax_save_post_mapping` | Map post ID to location |
| `edp_clear_override` | `ajax_clear_override` | Unlink CPT static page from location row |
| `edp_create_location_page` | `ajax_create_location_page` | Create CPT page for a location |
| `edp_delete_location_row` | `ajax_delete_location_row` | Delete one location row |
| `edp_delete_all_rows` | `ajax_delete_all_rows` | Delete all rows and Google data |
| `edp_check_pagespeed` | `ajax_check_pagespeed` | Run PageSpeed Insights |
| `edp_analyze_cqs` | `ajax_analyze_cqs` | Compute + cache CQS score |

### Admin-post actions (form submissions)

| Action | Handler |
|--------|---------|
| `edp_seo_save_settings` | `handle_save_settings()` |
| `edp_sheet_save_url` | `handle_sheet_save_url()` |
| `edp_sheet_sa_save` | `handle_sheet_sa_save()` |
| `edp_sheet_sa_clear` | `handle_sheet_sa_clear()` |
| `edp_seo_save_google` | `handle_save_google()` |

### Markdown doc viewer

`render_doc()` reads `admin/docs/{guide|architecture}.md`, converts to HTML via `markdown_to_html()`, and renders it in a styled viewer page. Supports headings, tables, lists, code blocks, blockquotes, bold, and inline code.

---

## 13. Locations List Table (`admin/class-edp-locations-list-table.php`)

Extends `WP_List_Table`. Handles pagination, filtering, sorting, bulk actions.

### Active filters (GET params)

| Param | Filters to |
|-------|-----------|
| `state_filter` | Exact state slug match |
| `city_filter` | City name LIKE search |
| `has_static=1` | `override_type = 'cpt'` rows |
| `has_mapped=1` | `override_type = 'mapped'` rows |
| `has_faq=1` | Rows whose CPT post has `_edp_faq_enabled = 1` (EXISTS subquery) |

Filters are combined with `AND`. The stat card filter toggles above the table link to these params directly.

---

## 14. Assets (`class-edp-assets.php`)

- **Admin CSS:** `admin/css/edp-admin.css` — unified stylesheet for all admin views. Uses `--edp-*` CSS custom properties in `:root`. Lato font via Google Fonts.
- **Front-end CSS:** Built from `src/css/main.css` (Tailwind utilities) → `assets/main.css` via `npm run build`.
- **Front-end JS:** `src/js/main.js` → `assets/main.js` (minimal, no framework).

---

## 15. Theme Integration

### View override

Place template files at:
```
{theme}/emergencydentalpros/views/city.php
{theme}/emergencydentalpros/views/state.php
{theme}/emergencydentalpros/views/index.php
```

### `$data` array passed to templates

| Key | Type | Value |
|-----|------|-------|
| `row` | array | Location DB row |
| `resolved` | array | Resolved content fields (meta, headings, body) |
| `faq_items` | array | FAQ Q&A pairs |
| `faq_enabled` | bool | Whether FAQ block is active |
| `nearby_businesses` | array | Google Places rows for this city |
| `other_cities` | array | Sibling cities in the same state |
| `page_url` | string | Canonical URL |

---

## 16. Extending the Plugin

### Adding a new template token

1. In `EDP_Template_Engine::base_vars()`, add `'key' => 'value'` to the returned array.
2. Available in all templates as `{key}`.

### Adding a new per-page meta field

1. Add the meta key to `EDP_CPT::META_KEYS`.
2. Add the input to `admin/views/city-settings-metabox.php`.
3. Add save logic in `EDP_CPT::save_metabox()`.
4. Read it in `EDP_Content_Resolver::resolve_city()` with the appropriate fallback.

### Adding a new CQS check

In `EDP_Cqs_Scorer::compute()`:
```php
self::check($breakdown, 'category_key', 'Check label', $points, $pass_bool);
```

### Adding a new Locations table filter

1. Read the GET param in `EDP_Locations_List_Table::prepare_items()`.
2. Append a condition to `$where_parts` (and `$where_values` if parameterised).
3. Add a toggle link in `admin/views/locations.php` stat card.

---

## 17. URL Logic & Migration

### 17.1 URL Modes

The plugin supports two URL structures, switchable in **Local SEO → Settings → URL Structure**:

| Mode | City URL | State URL | States index |
|------|----------|-----------|--------------|
| **Hierarchical** (default) | `/locations/{state_slug}/{city_slug}/` | `/locations/{state_slug}/` | `/locations/` |
| **Flat** | `/{city_slug}/` | `/{state_slug}/` | `/locations/` |

**City slug convention:** `{city}-{state-abbrev}` — e.g. `denton-tx`, `auburn-al`.

The active mode is stored in `EDP_Settings` as `url_mode` (`'hierarchical'` or `'flat'`).  
All URL generation goes through `EDP_Rewrite` static helpers — never hardcode `/locations/` paths:

```php
EDP_Rewrite::city_url($row);          // array with city_slug + state_slug keys
EDP_Rewrite::state_url($state_slug);
EDP_Rewrite::states_url();
```

Switching mode automatically flushes rewrite rules (handled in `EDP_Admin::handle_save_settings()`).

---

### 17.2 Routing — How a Request Reaches the Plugin

In flat mode the plugin registers a top-priority rewrite rule:
```
^([a-z0-9-]+)/?$ → index.php?edp_city_slug=$1
```

`EDP_View_Controller::bootstrap()` (fires at `template_redirect` priority 0) dispatches on `get_query_var`:

| Query var | Route | Handler |
|-----------|-------|---------|
| `edp_city_slug` | `VIEW_CITY_FLAT` | Look up city by slug only; serve CPT / mapped / global template |
| `edp_state_slug` | `VIEW_STATE` | Look up state by slug; render state city list |
| `edp_states_list` | `VIEW_STATES_LIST` | Render all-states index |
| `edp_auto` | `VIEW_AUTO` | Shared city/state handler for hierarchical mode |

In hierarchical mode, `VIEW_AUTO` uses both `edp_state_slug` and `edp_city_slug` query vars (set by the `/locations/{state}/{city}/` rewrite rule).

---

### 17.3 Content Resolution for a City Page

`EDP_Content_Resolver::resolve_city($row)` applies a three-tier cascade:

```
1. CPT post (override_type = 'cpt')
      └─ meta: _edp_body, _edp_meta_title, _edp_meta_description on the post
2. Mapped WP page (override_type = 'mapped')
      └─ post_content + Yoast/RankMath meta from the WP page
3. Global template
      └─ EDP_Template_Engine renders {tokens} from the location row + settings
```

The resolver reads `custom_post_id` and `override_type` from the location row.

---

### 17.4 Conflict Detection (Flat Mode Only)

When flat mode is active, a city slug like `denton-tx` must not be claimed by an existing WordPress page — otherwise WordPress serves the old page instead of the plugin's route.

`EDP_Database::find_wp_slug_conflicts_bulk(array $slugs): array` runs a single `IN` query:

```sql
SELECT ID, post_name, post_title, post_status, post_type
FROM wp_posts
WHERE post_name IN ('denton-tx', 'auburn-al', ...)
  AND post_status NOT IN ('trash', 'auto-draft')
  AND post_type IN ('page', 'post')
```

> **Note:** `draft` IS included — only a trashed or auto-draft post does not conflict.  
> A drafted page with its original slug still conflicts until the slug is renamed.

The admin Locations table displays a **⚠ Conflict** badge for each matching row (flat mode only, via `column_url_conflict()` in `EDP_Locations_List_Table`).

An admin can **Ignore** a conflict (stored in `wp_options edp_ignored_conflicts`) or **Migrate**.

---

### 17.5 Scenario A — Migrate (Same Slug)

Use case: a WordPress page at `/denton-tx/` needs to be replaced by the plugin's city page at the same URL.

**What happens on "Migrate & Take Over":**

```
1. Snapshot:  $content = old_page->post_content
              $seo_title, $seo_desc from Yoast (_yoast_wpseo_title) or RankMath (rank_math_title)

2. Archive old page:
   wp_update_post([
     'ID'          => $old_page_id,
     'post_status' => 'draft',
     'post_name'   => 'denton-tx--migrated',   ← slug freed immediately
   ])
   → Recoverable: WP Admin → Pages → Drafts (search "denton-tx--migrated")
   → Slug freed: no collision for the new CPT

3. Create CPT:
   wp_insert_post([
     'post_type'   => 'edp_seo_city',
     'post_status' => 'publish',
     'post_name'   => 'denton-tx',         ← correct slug, now available
     'post_content' => $content,
   ])
   On failure → restore old page (status=publish, post_name=denton-tx) → return error

4. Store CPT meta:
   _edp_location_id      = $location_id
   _edp_archived_post_id = $old_page_id    (reference, not used for routing)
   _edp_body             = wp_kses_post($content)
   _edp_meta_title       = sanitize_text_field($seo_title)
   _edp_meta_description = sanitize_textarea_field($seo_desc)

5. Update location row:  custom_post_id = $cpt_id, override_type = 'cpt'

6. Remove slug from edp_ignored_conflicts option

7. flush_rewrite_rules(false)
```

**Admin sees after reload:**
- Conflict badge gone (`denton-tx--migrated` no longer matches the conflict query)
- Override column → "Static Page" link to the new CPT
- `/denton-tx/` → plugin intercepts → CPT body served

**Same logic applies to `wp edp migrate {location_id}` CLI command.**

---

### 17.6 Scenario B — Adopt Non-Standard Slug (Custom URL)

Use case: an existing WP page lives at `/denton-texas-24-7/` (non-standard slug, not matching `xxx-yy`). The admin wants the plugin to manage this URL — injecting FAQ, schema, etc. — without changing the URL or redirecting away.

This is the **Map Post** flow, extended with "Keep original URL":

```
Admin: Local SEO → Locations → Map Post
       Enter post ID of /denton-texas-24-7/ page
       Toggle "Keep original URL" → Save
```

**Result in DB:**
- Location row: `custom_post_id = $wp_page_id`, `override_type = 'mapped'`
- WP page post meta: `_edp_no_redirect = 1`

**Routing after:**

| URL | Behaviour |
|-----|-----------|
| `/denton-texas-24-7/` | Served by WordPress normally. Plugin injects `wp_head` tags (canonical, meta desc, LocalBusiness schema) and `the_content` filter (appends FAQ) via hook registration |
| `/denton-tx/` (plugin canonical) | Plugin detects `override_type='mapped'` + `_edp_no_redirect=1` → 301 **to** `/denton-texas-24-7/` |

**Why:** avoids duplicate content (`/denton-tx/` now 301s to the canonical old URL), preserves SEO rankings at the old URL, and enables plugin content injection without moving the page.

> **Status:** Scenario B UI and routing are planned; `_edp_no_redirect` hook injection is not yet implemented. See `includes/class-edp-view-controller.php → redirect_mapped_posts()`.

---

### 17.7 Decision Tree — Which Scenario to Use

```
Does the old WP page slug match the city slug exactly?
├── YES → Scenario A (Migrate & Take Over)
│         Result: slug freed, old page archived as draft, CPT owns the URL.
└── NO  → Does the admin want the plugin to manage that old URL?
          ├── NO  → Leave alone. Two separate URLs coexist with different content.
          └── YES → Scenario B (Map Post + "Keep original URL")
                    Result: old URL canonical, /city-slug/ 301s to it.
```

---

### 17.8 Rewrite Rule Priority & Conflicts with Other Plugins

The plugin registers its city rewrite rule at `'top'` priority (WordPress `add_rewrite_rule` first arg `'top'`). This means it fires before any page/CPT rules.

Potential conflicts:
- **Yoast SEO / RankMath breadcrumb canonical** — these plugins respect WordPress's canonical URL; since the plugin outputs its own `<link rel="canonical">` via `EDP_View_Controller::output_canonical()`, ensure only one canonical tag is present.
- **Any plugin that also registers top-priority catch-all rewrite rules** — last-registered wins for equal-priority rules. Check `$wp_rewrite->rules` after plugin activation if routing breaks unexpectedly.
- **`.htaccess` caching layers** — page cache plugins may cache `/denton-tx/` before and after migration. Always run `flush_rewrite_rules()` (already done in migration) and purge the page cache for that URL.

After switching URL modes or migrating, always verify with:
```
wp rewrite flush --allow-root
wp eval 'echo EDP_Rewrite::city_url(["state_slug"=>"tx","city_slug"=>"denton-tx"]);'
```
