# Local SEO Plugin — Architecture Reference

Technical overview of how the plugin is structured, how requests are routed, how classes interact, and how to extend it.

---

## 1. Plugin Bootstrap (`emergencydentalpros.php`)

The main plugin file:
- Defines constants: `EDP_PLUGIN_VERSION`, `EDP_PLUGIN_DIR`, `EDP_PLUGIN_URL`
- `require_once`s all class files in dependency order
- Registers `activation_hook` → `EDP_Activator::activate()`
- On `plugins_loaded`: calls `EDP_Database::ensure_schema()`, registers rewrite rules, CPT, admin, view controller, and assets

**Load order matters:** `EDP_Database` → `EDP_Settings` → `EDP_Activator` → `EDP_Template_Engine` → `EDP_Schema` → `EDP_CPT` → `EDP_Content_Resolver` → `EDP_Cqs_Scorer` → `EDP_View_Controller` → `EDP_Admin`

---

## 2. Database Layer (`class-edp-database.php`)

All direct `$wpdb` queries go through this static class.

### Tables

| Table | Purpose |
|-------|---------|
| `{prefix}seo_locations` | Master location rows (state, city, slugs, ZIPs, override info) |
| `{prefix}seo_nearby_businesses` | Google Places results per location |
| `{prefix}seo_pagespeed_cache` | PageSpeed Insights scores + metrics |
| `{prefix}edp_cqs_cache` | Content Quality Scores + breakdown JSON |

### Schema versioning

`EDP_Activator::DB_VERSION` (currently `1.5.0`) is stored in `wp_options`. On every `plugins_loaded`, `EDP_Database::ensure_schema()` compares the stored version with the constant and calls `EDP_Activator::create_tables()` (via `dbDelta`) if they differ. This is the safe upgrade path — no destructive migrations.

---

## 3. Virtual Routing (`class-edp-rewrite.php`)

The plugin creates virtual pages without adding rows to `wp_posts`.

### Registered rewrite rules

| URL pattern | Query var |
|-------------|-----------|
| `locations/` | `edp_location_index=1` |
| `locations/{state}/` | `edp_state_slug={state}` |
| `locations/{state}/{city}/` | `edp_state_slug={state}&edp_city_slug={city}` |

These are added via `add_rewrite_rule()` in `EDP_Rewrite::register()` (hooked to `init`).

### Request interception

`EDP_View_Controller` hooks into `template_redirect`. When it detects one of the plugin's query vars:
1. Calls `EDP_View_Controller::build_view_data()` to resolve all content
2. Loads the appropriate template: `city.php`, `state.php`, or `index.php`
3. Calls `exit` — WordPress never loads its own template

**Theme override:** The view controller first checks `get_stylesheet_directory() . '/emergencydentalpros/views/{template}.php'`. If found, that theme file is used instead of the plugin default. This lets the active theme customize layouts without modifying plugin files.

---

## 4. Content Resolution (`class-edp-content-resolver.php`)

For city pages, content is resolved through a three-level priority chain:

```
CPT post meta override
    ↓ (if field is empty)
Mapped post content
    ↓ (if no mapped post)
Global City Landing template
```

`EDP_Content_Resolver::resolve_city(array $row): array` returns a flat array of all resolved fields: `meta_title`, `meta_description`, `h1`, `communities_h2`, `communities_body`, `other_cities_h2`.

Results are cached per-request in a static `$cache` array keyed by `location_id`.

---

## 5. Template Engine (`class-edp-template-engine.php`)

Handles `{token}` replacement in template strings.

### Key methods

- `base_vars(): array` — returns global tokens from settings (business name, phone, etc.)
- `context_from_city_row(array $base, array $row): array` — merges city-specific tokens (`{city}`, `{state}`, `{state_abbr}`, `{zip}`) onto `$base`
- `replace(string $template, array $vars): string` — performs the substitution

---

## 6. SEO Head Output (`class-edp-view-controller.php`)

On city pages, the following hooks fire in `wp_head`:

| Priority | Hook method | Output |
|----------|-------------|--------|
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
Outputs two schemas in one `<script type="application/ld+json">` block:
1. **Dentist** (extends LocalBusiness) — name, address, phone, URL from plugin settings
2. **BreadcrumbList** — 4 items: Home › Locations › {State} › {City}

### `output_faqpage_schema(array $faq_items): void`
Outputs **FAQPage** JSON-LD from the FAQ items array (Q/A pairs).

---

## 8. CPT — Static Page Overrides (`class-edp-cpt.php`)

Post type: `edp_seo_city` (not publicly queryable, shown in WP admin UI)

Each CPT post is linked to a location row via `_edp_location_id` post meta.

### Metaboxes registered

| Metabox | Meta keys |
|---------|-----------|
| Location Page Settings | `_edp_meta_title`, `_edp_meta_description`, `_edp_h1`, `_edp_communities_h2`, `_edp_communities_body`, `_edp_other_cities_h2` |
| FAQ Section | `_edp_faq_enabled`, `_edp_faq_h2`, `_edp_faq_intro`, `_edp_faq_items` (JSON array) |

### Save flow
Both metaboxes verify nonce + `current_user_can('edit_post')`. Text fields use `sanitize_text_field`, rich text fields use `wp_kses_post`.

---

## 9. Content Quality Score (`class-edp-cqs-scorer.php`)

Stateless scorer — `EDP_Cqs_Scorer::compute(int $location_id, array $row): array`

Evaluates 6 categories (100 pts total) based entirely on plugin-controlled data:

| Category | Max | Key signals |
|----------|-----|-------------|
| Title & Meta | 20 | meta_title override, meta_description override |
| Unique Content | 25 | has CPT (+10), communities_body override (+15) |
| Heading Structure | 15 | H1, communities H2, other cities H2 |
| FAQ Quality | 15 | enabled, has items, 5+ items |
| Local Business Data | 15 | google_count ≥ 1, google_count ≥ 5 |
| Schema | 10 | FAQPage active, BreadcrumbList (static page) |

Static pages max out at 100. Dynamic (template-only) pages cap at 85.

Results are stored in `{prefix}edp_cqs_cache` and displayed in the Locations table CQS column.

---

## 10. Admin Layer (`admin/class-edp-admin.php`)

Central class for all WP admin functionality.

### Registered AJAX actions

| Action | Handler | Description |
|--------|---------|-------------|
| `edp_google_import_step` | `ajax_google_import_step` | Incremental Google Places import |
| `edp_google_fetch_location` | `ajax_google_fetch_location` | Fetch one location |
| `edp_google_delete_location` | `ajax_google_delete_location` | Delete one location's Google data |
| `edp_sheet_sync_v2` | `ajax_sheet_sync_v2` | Google Sheets sync |
| `edp_save_post_mapping` | `ajax_save_post_mapping` | Map post ID to location |
| `edp_clear_override` | `ajax_clear_override` | Clear CPT static page link |
| `edp_create_location_page` | `ajax_create_location_page` | Create CPT page |
| `edp_delete_location_row` | `ajax_delete_location_row` | Delete one location row |
| `edp_delete_all_rows` | `ajax_delete_all_rows` | Delete all rows |
| `edp_check_pagespeed` | `ajax_check_pagespeed` | Run PageSpeed Insights |
| `edp_analyze_cqs` | `ajax_analyze_cqs` | Compute CQS score |

---

## 11. Assets (`class-edp-assets.php`)

- **Admin CSS:** `admin/css/edp-admin.css` — single unified stylesheet for all admin views. Uses `--edp-*` CSS custom properties defined in `:root`.
- **Front-end CSS:** `assets/main.css` — city page styles.
- **Front-end JS:** none (no JS shipped to visitors).

---

## 12. Theme Integration

### View override

Place template files at:
```
{theme}/emergencydentalpros/views/city.php
{theme}/emergencydentalpros/views/state.php
{theme}/emergencydentalpros/views/index.php
```

The view controller checks for these before loading plugin defaults. Theme templates receive the same `$data` array.

### `$data` array passed to templates

| Key | Value |
|-----|-------|
| `row` | Location DB row |
| `resolved` | Resolved content fields (meta, headings, body) |
| `faq_items` | FAQ Q&A pairs |
| `faq_enabled` | bool |
| `nearby_businesses` | Array of Google Places rows |
| `other_cities` | Sibling cities in the same state |
| `page_url` | Canonical URL |

---

## 13. Extending the Plugin

### Adding a new template token

1. In `EDP_Template_Engine::base_vars()`, add your key → value to the returned array.
2. It will be available in all templates as `{your_token}`.

### Adding a new per-page meta field

1. Add the meta key to `EDP_CPT::META_KEYS`.
2. Add the input to `admin/views/city-settings-metabox.php`.
3. Add save logic in `EDP_CPT::save_metabox()`.
4. Read it in `EDP_Content_Resolver::resolve_city()` with the appropriate fallback.

### Adding a new CQS check

In `EDP_Cqs_Scorer::compute()`, call `self::check($breakdown, 'category_key', 'Label', $pts, $pass_bool)` within the relevant category block.
