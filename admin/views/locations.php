<?php
/**
 * Locations manager.
 *
 * @package EmergencyDentalPros
 *
 * @var EDP_Locations_List_Table $table
 * @var int                      $location_count
 * @var string                   $default_csv
 * @var bool                     $default_csv_ok
 * @var array<string, mixed>     $import_log
 * @var bool                       $edp_seo_debug
 * @var array<string, mixed>       $edp_debug_data
 * @var array<string, mixed>|null  $edp_google_notice
 */

if (!defined('ABSPATH')) {
	exit;
}

$edp_seo_debug    = $edp_seo_debug ?? false;
$edp_debug_data   = $edp_debug_data ?? [];
$edp_google_notice = isset($edp_google_notice) && is_array($edp_google_notice) ? $edp_google_notice : null;

?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap');

/* ════════════════════════════════════════════════════
   EDP Locations Admin — scoped to #edp-locations-wrap
   ════════════════════════════════════════════════════ */

/* ── 1. Design Tokens ─────────────────────────────── */
#edp-locations-wrap {
	--c-brand:       #6E39CB;
	--c-brand-ring:  rgba(110,57,203,.15);
	--c-type:        #3A3541;
	--c-muted:       #89868D;
	--c-surface:     #F4F5F9;
	--c-surface2:    #E7E7F4;
	--c-border:      #DBDCDE;
	--c-white:       #fff;
	--c-shadow:      0 0 4px rgba(0,0,0,.15);
	--c-ok:          #0a7040;
	--c-ok-bg:       #f0faf4;
	--c-ok-bd:       #2ecc71;
	--c-err:         #b32d2e;
	--c-err-bg:      #fff5f5;
	--c-err-bd:      #e74c3c;
	--c-warn:        #7a5200;
	--c-warn-bg:     #fff3cd;
	--c-warn-bd:     #f0b429;
	--c-info-tx:     #1a4a7a;
	--c-info-bg:     #f0f6fc;
	--c-info-bd:     #2271b1;
	--r-card:        8px;
	--r-btn:         4px;
	--r-input:       8px;
	--h-btn:         32px;
	--h-btn-sm:      26px;
	font-family: 'Lato', sans-serif;
	color: var(--c-type);
}

/* ── 2. Page Shell ────────────────────────────────── */
#edp-locations-wrap h1 {
	font-family: 'Lato', sans-serif;
	font-weight: 700;
	font-size: 25.63px;
	color: var(--c-type);
	margin-bottom: 4px;
}
#edp-locations-wrap .edp-subtitle {
	font-size: 14.22px;
	color: var(--c-muted);
	margin-top: 0;
	margin-bottom: 24px;
}

/* ── 3. Notices ───────────────────────────────────── */
.edp-notice {
	border-radius: var(--r-card);
	padding: 12px 16px;
	margin-bottom: 16px;
	font-size: 14.22px;
	font-family: 'Lato', sans-serif;
}
.edp-notice ul { margin: 6px 0 0; padding-left: 1.25em; list-style: disc; }
.edp-notice-success { background: var(--c-ok-bg);   border-left: 4px solid var(--c-ok-bd);   color: var(--c-ok); }
.edp-notice-error   { background: var(--c-err-bg);  border-left: 4px solid var(--c-err-bd);  color: var(--c-err); }
.edp-notice-warning { background: var(--c-warn-bg); border-left: 4px solid var(--c-warn-bd); color: var(--c-warn); }
.edp-notice-info    { background: var(--c-info-bg); border-left: 4px solid var(--c-info-bd); color: var(--c-info-tx); }

/* ── 4. Stat Cards ────────────────────────────────── */
.edp-stat-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 24px;
}
.edp-stat-card {
	background: var(--c-white);
	border-radius: var(--r-card);
	box-shadow: var(--c-shadow);
	padding: 20px;
	font-family: 'Lato', sans-serif;
}
.edp-stat-card-title {
	font-weight: 700;
	font-size: 25.63px;
	color: var(--c-type);
	margin: 0 0 4px;
	line-height: 1;
}
.edp-stat-card-sub {
	font-size: 14.22px;
	color: var(--c-muted);
	margin: 0 0 16px;
}
.edp-stat-row-items { display: flex; flex-direction: column; gap: 8px; }
.edp-stat-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	font-size: 14.22px;
	color: var(--c-type);
}
.edp-stat-item .dashicons { flex-shrink: 0; font-size: 16px; width: 16px; height: 16px; margin-top: 2px; }
.edp-stat-item .dashicons-yes     { color: var(--c-ok); }
.edp-stat-item .dashicons-warning { color: var(--c-err); }
.edp-stat-item .dashicons-info    { color: #2271b1; }
.edp-stat-label { color: var(--c-muted); }
.edp-stat-val   { color: var(--c-type); font-weight: 500; }
.edp-stat-err   { color: var(--c-err); font-size: 12.64px; margin-top: 2px; }
.edp-stat-card-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 16px;
	padding-top: 14px;
	border-top: 1px solid var(--c-border);
}

/* ── 5. Buttons ───────────────────────────────────── */
.edp-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: var(--r-btn);
	height: var(--h-btn);
	padding: 0 16px;
	font-size: 12.64px;
	font-family: 'Lato', sans-serif;
	cursor: pointer;
	text-decoration: none;
	transition: opacity .15s;
	line-height: 1;
}
.edp-btn:hover          { opacity: .88; text-decoration: none; }
.edp-btn-primary        { background: var(--c-brand); color: var(--c-white) !important; }
.edp-btn-secondary      { background: var(--c-white); color: var(--c-type) !important; box-shadow: var(--c-shadow); }

/* ── 6. Diagnostics Card ──────────────────────────── */
.edp-diag-card {
	background: var(--c-white);
	border-radius: var(--r-card);
	box-shadow: var(--c-shadow);
	margin-bottom: 24px;
	overflow: hidden;
}
.edp-diag-header {
	background: var(--c-warn-bg);
	padding: 12px 20px;
	font-weight: 500;
	font-size: 14.22px;
	color: var(--c-warn);
	display: flex;
	align-items: center;
	gap: 8px;
}
.edp-diag-body { padding: 16px 20px; }
.edp-diag-body p { font-size: 13px; color: var(--c-muted); margin: 0 0 8px; }
.edp-diag-body pre {
	max-height: 420px;
	overflow: auto;
	background: #1d2327;
	color: #f0f0f1;
	padding: 12px;
	font-size: 12px;
	border-radius: 6px;
	margin: 0;
}

/* ── 7. Table Card ────────────────────────────────── */
.edp-table-card {
	background: var(--c-white);
	border-radius: var(--r-card);
	box-shadow: var(--c-shadow);
	overflow: hidden;
}
.edp-table-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	padding: 20px 20px 0;
}
.edp-table-header h2 {
	font-weight: 700;
	font-size: 25.63px;
	color: var(--c-type);
	margin: 0 0 4px;
	padding: 0;
	line-height: 1;
	border: none;
}
.edp-table-header p { font-size: 14.22px; color: var(--c-muted); margin: 0; }

/* ── 8. WP List Table Overrides ───────────────────── */
#edp-locations-wrap .tablenav {
	padding: 10px 20px;
	display: flex;
	align-items: center;
}
#edp-locations-wrap .tablenav.top    { border-bottom: 1px solid var(--c-border); }
#edp-locations-wrap .tablenav.bottom { border-top: 1px solid var(--c-border); }
#edp-locations-wrap .tablenav .tablenav-pages { font-family: 'Lato', sans-serif; font-size: 13px; color: var(--c-muted); }
#edp-locations-wrap .tablenav .button { font-family: 'Lato', sans-serif; border-radius: var(--r-btn); }
#edp-locations-wrap .search-box input[type="search"] {
	background: var(--c-surface);
	border: 1px solid var(--c-border);
	border-radius: var(--r-input);
	padding: 6px 12px;
	font-family: 'Lato', sans-serif;
	font-size: 14.22px;
	color: var(--c-type);
}
#edp-locations-wrap .search-box input[type="search"]:focus {
	outline: none;
	border-color: var(--c-brand);
	box-shadow: 0 0 0 2px var(--c-brand-ring);
}
#edp-locations-wrap .search-box .button {
	background: var(--c-brand);
	color: var(--c-white);
	border-color: var(--c-brand);
	border-radius: var(--r-btn);
	font-family: 'Lato', sans-serif;
}
#edp-locations-wrap table.wp-list-table {
	width: 100%;
	border-collapse: collapse;
	border: none;
	box-shadow: none;
	background: transparent;
	font-family: 'Lato', sans-serif;
}
#edp-locations-wrap table.wp-list-table thead th,
#edp-locations-wrap table.wp-list-table thead td {
	background: var(--c-white);
	font-family: 'Lato', sans-serif;
	font-weight: 500;
	font-size: 16px;
	color: var(--c-type);
	border-bottom: 1px solid var(--c-border);
	border-top: none;
	padding: 14px 20px;
	white-space: nowrap;
}
#edp-locations-wrap table.wp-list-table thead th a,
#edp-locations-wrap table.wp-list-table thead th a:hover { color: var(--c-type); font-weight: 500; }
#edp-locations-wrap table.wp-list-table thead th.sorted,
#edp-locations-wrap table.wp-list-table thead th.asc,
#edp-locations-wrap table.wp-list-table thead th.desc { background: var(--c-surface); }
#edp-locations-wrap table.wp-list-table tbody tr {
	border-bottom: 1px solid var(--c-surface);
	background: var(--c-white);
	transition: background .1s;
}
#edp-locations-wrap table.wp-list-table tbody tr:hover { background: #fafafa; }
#edp-locations-wrap table.wp-list-table tbody tr.alternate { background: var(--c-white); }
#edp-locations-wrap table.wp-list-table tbody td,
#edp-locations-wrap table.wp-list-table tbody th {
	border-bottom: 1px solid var(--c-surface);
	border-top: none;
	padding: 14px 20px;
	font-size: 14.22px;
	color: var(--c-muted);
	vertical-align: middle;
	font-family: 'Lato', sans-serif;
}
#edp-locations-wrap table.wp-list-table tbody td.column-primary,
#edp-locations-wrap table.wp-list-table tbody th.column-primary { color: var(--c-type); font-weight: 500; }
#edp-locations-wrap table.wp-list-table .row-actions { font-size: 12.64px; color: var(--c-muted); }
#edp-locations-wrap table.wp-list-table .row-actions a { color: var(--c-brand); }
#edp-locations-wrap table.wp-list-table .row-actions .delete a,
#edp-locations-wrap table.wp-list-table .row-actions .trash a { color: var(--c-err); }

/* ── 9. Table Cell Components ─────────────────────── */

/* Google Business cell */
#edp-locations-wrap .edp-listing-cell { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
#edp-locations-wrap .edp-listing-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 13px; line-height: 1; font-family: 'Lato', sans-serif; }
#edp-locations-wrap .edp-listing-badge--has   { color: var(--c-ok); font-weight: 600; }
#edp-locations-wrap .edp-listing-badge--empty { color: var(--c-muted); }
#edp-locations-wrap .edp-listing-badge .dashicons { font-size: 15px; width: 15px; height: 15px; }
#edp-locations-wrap .edp-listing-btns { display: inline-flex; gap: 4px; }
#edp-locations-wrap .edp-listing-cell--loading { opacity: .45; pointer-events: none; }

/* Shared small button */
#edp-locations-wrap .edp-listing-btn {
	background: var(--c-white) !important;
	border: 1px solid var(--c-border) !important;
	border-radius: var(--r-btn) !important;
	color: var(--c-type) !important;
	padding: 3px 8px !important;
	min-height: var(--h-btn-sm) !important;
	line-height: 1 !important;
	display: inline-flex !important;
	align-items: center !important;
	gap: 3px;
	font-size: 12px !important;
	font-family: 'Lato', sans-serif !important;
	cursor: pointer;
	transition: background .12s, border-color .12s;
}
#edp-locations-wrap .edp-listing-btn:hover {
	background: var(--c-surface) !important;
	border-color: var(--c-brand) !important;
	color: var(--c-brand) !important;
}
#edp-locations-wrap .edp-listing-btn .dashicons { font-size: 13px; width: 13px; height: 13px; }
#edp-locations-wrap .edp-listing-btn--danger { color: var(--c-err) !important; border-color: var(--c-err) !important; }
#edp-locations-wrap .edp-listing-btn--danger:hover { background: var(--c-err-bg) !important; }

/* Static Page cell */
#edp-locations-wrap .edp-static-page-cell { display: inline-flex; align-items: center; gap: 8px; }
#edp-locations-wrap .edp-page-link {
	color: var(--c-brand);
	font-weight: 500;
	font-size: 13px;
	text-decoration: none;
}
#edp-locations-wrap .edp-page-link:hover { text-decoration: underline; }
#edp-locations-wrap .edp-btn-create {
	gap: 4px;
}
#edp-locations-wrap .edp-page-link--dead {
	color: var(--c-muted);
	font-size: 13px;
	font-style: italic;
}

/* Map Post wrap + clear button */
#edp-locations-wrap .edp-map-post-wrap {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
#edp-locations-wrap .edp-map-clear-btn {
	background: none;
	border: none;
	color: var(--c-muted);
	cursor: pointer;
	font-size: 16px;
	line-height: 1;
	padding: 2px 4px;
	border-radius: var(--r-btn);
	transition: color .12s;
}
#edp-locations-wrap .edp-map-clear-btn:hover { color: var(--c-err); }

/* Map Post input */
#edp-locations-wrap .edp-map-post-input {
	background: var(--c-surface);
	border: 1px solid var(--c-border);
	border-radius: var(--r-input);
	padding: 5px 10px;
	font-size: 13px;
	font-family: 'Lato', sans-serif;
	color: var(--c-type);
	width: 90px;
	transition: border-color .15s, box-shadow .15s;
}
#edp-locations-wrap .edp-map-post-input:focus {
	outline: none;
	border-color: var(--c-brand);
	box-shadow: 0 0 0 2px var(--c-brand-ring);
}
#edp-locations-wrap .edp-map-post-input.edp-input--error {
	border-color: var(--c-err-bd);
	box-shadow: 0 0 0 2px rgba(231,76,60,.15);
}

/* ── 10. Column Filter / Sort UI ──────────────────── */

/* Filter icon button inside <th> */
.edp-col-filter-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: none;
	border: none;
	cursor: pointer;
	padding: 2px 3px;
	margin-left: 4px;
	border-radius: 3px;
	color: var(--c-muted);
	vertical-align: middle;
	transition: color .15s, background .15s;
	line-height: 1;
}
.edp-col-filter-btn .dashicons { font-size: 14px; width: 14px; height: 14px; }
.edp-col-filter-btn:hover { color: var(--c-brand); background: var(--c-surface2); }
.edp-col-filter-btn.edp-col-filter-btn--active { color: var(--c-brand); }

/* Active filter chips row */
.edp-active-filters {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	padding: 8px 20px 0;
	font-family: 'Lato', sans-serif;
}
.edp-filter-chip {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	background: var(--c-surface2);
	border: 1px solid var(--c-brand);
	color: var(--c-brand);
	border-radius: 20px;
	padding: 3px 10px 3px 12px;
	font-size: 12.64px;
	font-weight: 500;
	white-space: nowrap;
}
.edp-filter-chip-remove {
	background: none;
	border: none;
	cursor: pointer;
	color: var(--c-brand);
	font-size: 15px;
	line-height: 1;
	padding: 0 0 1px;
	opacity: .7;
	transition: opacity .12s;
}
.edp-filter-chip-remove:hover { opacity: 1; }
.edp-clear-all-filters {
	font-size: 12.64px;
	color: var(--c-muted);
	text-decoration: none;
	padding: 2px 6px;
}
.edp-clear-all-filters:hover { color: var(--c-err); text-decoration: underline; }

/* Popover container (rendered in <body>) */
.edp-filter-popover {
	position: fixed;
	z-index: 99999;
	background: var(--c-white);
	border: 1px solid var(--c-border);
	border-radius: var(--r-card, 8px);
	box-shadow: 0 4px 20px rgba(0,0,0,.15);
	min-width: 220px;
	max-width: 300px;
	font-family: 'Lato', sans-serif;
	overflow: hidden;
}
.edp-filter-popover-header {
	padding: 10px 14px 8px;
	font-size: 12px;
	font-weight: 700;
	color: var(--c-muted);
	text-transform: uppercase;
	letter-spacing: .04em;
	border-bottom: 1px solid var(--c-border);
}
.edp-filter-popover-search {
	padding: 10px 12px;
	display: flex;
	gap: 6px;
	border-bottom: 1px solid var(--c-border);
}
.edp-filter-search-input {
	flex: 1;
	background: var(--c-surface);
	border: 1px solid var(--c-border);
	border-radius: 6px;
	padding: 5px 10px;
	font-size: 13px;
	font-family: 'Lato', sans-serif;
	color: var(--c-type);
	outline: none;
	transition: border-color .15s, box-shadow .15s;
}
.edp-filter-search-input:focus {
	border-color: var(--c-brand);
	box-shadow: 0 0 0 2px var(--c-brand-ring);
}
.edp-filter-apply-btn {
	background: var(--c-brand);
	color: #fff;
	border: none;
	border-radius: 6px;
	padding: 5px 12px;
	font-size: 13px;
	font-family: 'Lato', sans-serif;
	cursor: pointer;
	white-space: nowrap;
	transition: opacity .15s;
}
.edp-filter-apply-btn:hover { opacity: .88; }

/* Options list */
.edp-filter-options-list {
	list-style: none;
	margin: 0;
	padding: 4px 0;
	max-height: 220px;
	overflow-y: auto;
}
.edp-filter-options-list li a,
.edp-filter-options-list li button {
	display: block;
	width: 100%;
	text-align: left;
	padding: 7px 14px;
	font-size: 13.5px;
	color: var(--c-type);
	text-decoration: none;
	background: none;
	border: none;
	cursor: pointer;
	font-family: 'Lato', sans-serif;
	transition: background .1s;
	box-sizing: border-box;
}
.edp-filter-options-list li a:hover,
.edp-filter-options-list li button:hover { background: var(--c-surface); }
.edp-filter-options-list li.edp-filter-option--active a,
.edp-filter-options-list li.edp-filter-option--active button {
	color: var(--c-brand);
	font-weight: 600;
	background: rgba(110,57,203,.06);
}
.edp-filter-options-empty {
	padding: 10px 14px;
	font-size: 13px;
	color: var(--c-muted);
}

/* Clear link at bottom of popover */
.edp-filter-clear-link {
	display: block;
	padding: 8px 14px;
	font-size: 12.64px;
	color: var(--c-err);
	text-decoration: none;
	border-top: 1px solid var(--c-border);
	text-align: center;
}
.edp-filter-clear-link:hover { background: var(--c-err-bg); }
</style>

<div id="edp-locations-wrap" class="wrap">
	<h1><?php esc_html_e('Local SEO — Locations', 'emergencydentalpros'); ?></h1>
	<p class="edp-subtitle"><?php esc_html_e('Map locations to posts, create static page overrides, or run Google Places fetch per city.', 'emergencydentalpros'); ?></p>

	<?php /* ── Flash notices ── */ ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['google_none'])) : ?>
		<div class="edp-notice edp-notice-warning"><?php esc_html_e('No locations were selected.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['pages_created'])) :
		$_pages_created = (int) $_GET['pages_created'];
		$_pages_skipped = isset($_GET['pages_skipped']) ? (int) $_GET['pages_skipped'] : 0;
	?>
		<div class="edp-notice edp-notice-success">
			<?php printf(
				/* translators: 1: created count, 2: skipped count */
				esc_html__('Created %1$d static page(s). Skipped %2$d (already exist or errors).', 'emergencydentalpros'),
				(int) $_pages_created,
				(int) $_pages_skipped
			); ?>
		</div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['rows_deleted'])) : ?>
		<div class="edp-notice edp-notice-success">
			<?php printf(
				/* translators: %d: number of deleted rows */
				esc_html__('Deleted %d location row(s).', 'emergencydentalpros'),
				(int) $_GET['rows_deleted']
			); ?>
		</div>
	<?php endif; ?>

	<?php if ($edp_google_notice !== null) :
		$proc  = (int) ($edp_google_notice['processed'] ?? 0);
		$calls = (int) ($edp_google_notice['api_calls'] ?? 0);
		$msgs  = isset($edp_google_notice['messages']) && is_array($edp_google_notice['messages']) ? $edp_google_notice['messages'] : [];
		$g_ok  = !empty($edp_google_notice['ok']);
		$g_err = isset($edp_google_notice['error']) ? (string) $edp_google_notice['error'] : '';

		$notice_class = 'edp-notice-info';
		if (!$g_ok && $g_err === 'missing_api_key') {
			$notice_class = 'edp-notice-error';
		} elseif ($msgs === [] && $g_ok) {
			$notice_class = 'edp-notice-success';
		} elseif ($msgs !== [] || !$g_ok) {
			$notice_class = 'edp-notice-warning';
		}
	?>
		<div class="edp-notice <?php echo esc_attr($notice_class); ?>">
			<?php if (!$g_ok && $g_err === 'missing_api_key') : ?>
				<?php esc_html_e('Google Places API key is missing. Add it under Local SEO → Import.', 'emergencydentalpros'); ?>
			<?php else : ?>
				<?php
				printf(
					/* translators: 1: locations processed, 2: API calls */
					esc_html__('Google fetch finished — locations processed: %1$d — API calls (approx.): %2$d', 'emergencydentalpros'),
					(int) $proc,
					(int) $calls
				);
				?>
			<?php endif; ?>
			<?php if ($msgs !== []) : ?>
				<ul>
					<?php foreach (array_slice($msgs, 0, 15) as $m) : ?>
						<li><code><?php echo esc_html((string) $m); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php /* ── Stat cards ── */ ?>
	<div class="edp-stat-row">

		<?php /* Card 1 — Database */ ?>
		<div class="edp-stat-card">
			<p class="edp-stat-card-title"><?php echo esc_html(number_format_i18n($location_count)); ?></p>
			<p class="edp-stat-card-sub"><?php esc_html_e('Location rows in database', 'emergencydentalpros'); ?></p>
			<div class="edp-stat-row-items">
				<?php if (!empty($import_log['at'])) :
					$import_date = esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $import_log['at']));
					$has_err = !empty($import_log['error']) || (isset($import_log['ok']) && !$import_log['ok']);
				?>
					<div class="edp-stat-item">
						<span class="dashicons <?php echo $has_err ? 'dashicons-warning' : 'dashicons-yes'; ?>"></span>
						<div>
							<span class="edp-stat-label"><?php esc_html_e('Last import:', 'emergencydentalpros'); ?></span>
							<span class="edp-stat-val"> <?php echo esc_html($import_date); ?></span>
							<br />
							<span style="font-size:12.64px; color:#89868D;">
								<?php
								printf(
									/* translators: 1: rows, 2: skipped, 3: groups */
									esc_html__('rows %1$d, skipped %2$d, city groups %3$d', 'emergencydentalpros'),
									(int) ($import_log['rows'] ?? 0),
									(int) ($import_log['skipped'] ?? 0),
									(int) ($import_log['groups'] ?? 0)
								);
								?>
							</span>
							<?php if (!empty($import_log['path'])) : ?>
								<br /><code style="font-size:11px;"><?php echo esc_html((string) $import_log['path']); ?></code>
							<?php endif; ?>
							<?php if ($has_err) :
								$err_code = isset($import_log['error']) ? (string) $import_log['error'] : '';
								$err_msgs = [
									'file_not_readable'        => __('CSV could not be read (wrong path or permissions).', 'emergencydentalpros'),
									'fopen_failed'             => __('Could not open CSV file.', 'emergencydentalpros'),
									'empty_or_invalid_csv'     => __('CSV was empty or invalid.', 'emergencydentalpros'),
									'missing_columns'          => __('CSV is missing required columns (zip, city, state_id, state_name).', 'emergencydentalpros'),
									'custom_path_not_readable' => __('The custom CSV path was not readable.', 'emergencydentalpros'),
								];
							?>
								<br /><span class="edp-stat-err"><?php echo esc_html($err_msgs[$err_code] ?? __('Import reported an error. Check the path and try again.', 'emergencydentalpros')); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php else : ?>
					<div class="edp-stat-item">
						<span class="dashicons dashicons-info"></span>
						<span class="edp-stat-label"><?php esc_html_e('No import log yet.', 'emergencydentalpros'); ?></span>
					</div>
				<?php endif; ?>

				<?php
				$rows_read = (int) ($import_log['rows'] ?? 0);
				$groups    = (int) ($import_log['groups'] ?? 0);
				if ($location_count === 0 && $rows_read > 0 && $groups === 0 && empty($import_log['error'])) : ?>
					<div class="edp-stat-item">
						<span class="dashicons dashicons-warning"></span>
						<span style="font-size:13px; color:#7a5200;"><?php esc_html_e('Last import read rows but produced zero city groups. Confirm your CSV contains US rows.', 'emergencydentalpros'); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php /* Card 2 — CSV + actions */ ?>
		<div class="edp-stat-card">
			<p class="edp-stat-card-title"><?php esc_html_e('Data Sources', 'emergencydentalpros'); ?></p>
			<p class="edp-stat-card-sub"><?php esc_html_e('Front-end URLs: /locations/ — state list; /locations/{state}/ — cities; /locations/{state}/{city}/ — city landing.', 'emergencydentalpros'); ?></p>
			<div class="edp-stat-row-items">
				<div class="edp-stat-item">
					<span class="dashicons <?php echo $default_csv_ok ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
					<div>
						<span class="edp-stat-label"><?php esc_html_e('Default CSV:', 'emergencydentalpros'); ?></span>
						<code style="font-size:12px; word-break:break-all;"> <?php echo esc_html($default_csv); ?></code>
						<br />
						<?php if ($default_csv_ok) : ?>
							<span style="color:#0a7040; font-size:12.64px;"><?php esc_html_e('readable', 'emergencydentalpros'); ?></span>
						<?php else : ?>
							<span class="edp-stat-err"><?php esc_html_e('not found — use Import or add raw_data.csv on the server.', 'emergencydentalpros'); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="edp-stat-card-actions">
				<a class="edp-btn edp-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=edp-seo-import')); ?>"><?php esc_html_e('Go to Import', 'emergencydentalpros'); ?></a>
				<?php if (!$edp_seo_debug) : ?>
					<a class="edp-btn edp-btn-secondary" href="<?php echo esc_url(add_query_arg('edp_seo_debug', '1', admin_url('admin.php?page=edp-seo-locations'))); ?>"><?php esc_html_e('Show diagnostics', 'emergencydentalpros'); ?></a>
				<?php else : ?>
					<a class="edp-btn edp-btn-secondary" href="<?php echo esc_url(remove_query_arg('edp_seo_debug', admin_url('admin.php?page=edp-seo-locations'))); ?>"><?php esc_html_e('Hide diagnostics', 'emergencydentalpros'); ?></a>
				<?php endif; ?>
				<button type="button" id="edp-delete-all-btn" class="edp-listing-btn edp-listing-btn--danger"
					data-nonce="<?php echo esc_attr(wp_create_nonce('edp_delete_all_rows')); ?>"
					title="<?php esc_attr_e('Permanently delete all location rows and their Google data', 'emergencydentalpros'); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e('Delete All Rows', 'emergencydentalpros'); ?>
				</button>
			</div>
		</div>
	</div>

	<?php /* ── Diagnostics ── */ ?>
	<?php if ($edp_seo_debug && !empty($edp_debug_data)) : ?>
		<div class="edp-diag-card">
			<div class="edp-diag-header">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e('Diagnostics (admins only)', 'emergencydentalpros'); ?>
			</div>
			<div class="edp-diag-body">
				<p><?php esc_html_e('Enable with ?edp_seo_debug=1, option edp_seo_debug_panel, or define EDP_SEO_DEBUG in wp-config.php. Use to verify screen hook, columns, and SQL.', 'emergencydentalpros'); ?></p>
				<pre><?php echo esc_html(print_r($edp_debug_data, true)); ?></pre>
			</div>
		</div>
	<?php endif; ?>

	<?php /* ── Table card ── */ ?>
	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edp_state_filter = isset($_GET['state_filter']) ? sanitize_text_field(wp_unslash($_GET['state_filter'])) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edp_city_filter  = isset($_GET['city_filter']) ? sanitize_text_field(wp_unslash($_GET['city_filter'])) : '';
	$edp_has_filters  = $edp_state_filter !== '' || $edp_city_filter !== '';
	$edp_base_url     = admin_url('admin.php?page=edp-seo-locations');
	?>
	<div class="edp-table-card">
		<div class="edp-table-header">
			<div>
				<h2><?php esc_html_e('Locations', 'emergencydentalpros'); ?></h2>
				<p><?php esc_html_e('Map a post ID, create a static page from templates, or fetch Google Business data.', 'emergencydentalpros'); ?></p>
			</div>
		</div>
		<?php if ($edp_has_filters) : ?>
		<div class="edp-active-filters">
			<?php if ($edp_state_filter !== '') :
				$edp_state_label = '';
				foreach (EDP_Database::get_distinct_states() as $s) {
					if ($s['state_slug'] === $edp_state_filter) { $edp_state_label = $s['state_name'] . ' (' . strtoupper($s['state_id']) . ')'; break; }
				}
				$edp_clear_state = esc_url(add_query_arg(['state_filter' => false, 'paged' => false], $edp_base_url));
			?>
			<span class="edp-filter-chip">
				<?php esc_html_e('State:', 'emergencydentalpros'); ?> <strong><?php echo esc_html($edp_state_label ?: $edp_state_filter); ?></strong>
				<a href="<?php echo $edp_clear_state; ?>" class="edp-filter-chip-remove" title="<?php esc_attr_e('Remove filter', 'emergencydentalpros'); ?>">&times;</a>
			</span>
			<?php endif; ?>
			<?php if ($edp_city_filter !== '') :
				$edp_clear_city = esc_url(add_query_arg(['city_filter' => false, 'paged' => false], $edp_base_url));
			?>
			<span class="edp-filter-chip">
				<?php esc_html_e('City:', 'emergencydentalpros'); ?> <strong><?php echo esc_html($edp_city_filter); ?></strong>
				<a href="<?php echo $edp_clear_city; ?>" class="edp-filter-chip-remove" title="<?php esc_attr_e('Remove filter', 'emergencydentalpros'); ?>">&times;</a>
			</span>
			<?php endif; ?>
			<a href="<?php echo esc_url($edp_base_url); ?>" class="edp-clear-all-filters"><?php esc_html_e('Clear all', 'emergencydentalpros'); ?></a>
		</div>
		<?php endif; ?>
		<form id="edp-locations-filter" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
			<?php $table->display(); ?>
		</form>
	</div>
</div>

<?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
<script>window.edpDevSeedNonce = <?php echo wp_json_encode(wp_create_nonce('edp_dev_seed_csv')); ?>;</script>
<?php endif; ?>
<script>
(function () {
	var nonces = {
		mapPost:       <?php echo wp_json_encode(wp_create_nonce('edp_map_post')); ?>,
		clearOverride: <?php echo wp_json_encode(wp_create_nonce('edp_clear_override')); ?>,
		createPage:    <?php echo wp_json_encode(wp_create_nonce('edp_create_location_page')); ?>,
		deleteRow:     <?php echo wp_json_encode(wp_create_nonce('edp_delete_location_row')); ?>,
	};

	var errMsg       = <?php echo wp_json_encode(__('An error occurred.', 'emergencydentalpros')); ?>;
	var confirmClear = <?php echo wp_json_encode(__('Remove static page override? The linked WordPress post will be permanently deleted.', 'emergencydentalpros')); ?>;
	var confirmDeleteRow = <?php echo wp_json_encode(__('Permanently delete this location row? This also removes Google data and the linked CPT post.', 'emergencydentalpros')); ?>;

	/* ── Google Business listing buttons ──────────────── */
	function attachListingBtn(btn) {
		btn.addEventListener('click', function () {
			var locationId = this.dataset.locationId;
			var action     = this.dataset.listingAction;
			var nonce      = this.dataset.nonce;
			var cell       = this.closest('td');
			var original   = cell.innerHTML;

			cell.querySelector('.edp-listing-cell').classList.add('edp-listing-cell--loading');

			var body = new URLSearchParams({
				action:      action === 'fetch' ? 'edp_google_fetch_location' : 'edp_google_delete_location',
				nonce:       nonce,
				location_id: locationId,
			});

			fetch(ajaxurl, { method: 'POST', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						cell.innerHTML = json.data.html;
						cell.querySelectorAll('.edp-listing-btn').forEach(attachListingBtn);
					} else {
						cell.innerHTML = original;
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					cell.innerHTML = original;
				});
		});
	}

	document.querySelectorAll('.edp-listing-btn[data-listing-action]').forEach(attachListingBtn);

	/* ── Map Post input — AJAX save on Enter / blur ───── */
	function saveMapPost(input) {
		var postId     = input.value.trim();
		var locationId = input.dataset.locationId;

		if (postId === '' || postId === '0') {
			return;
		}

		fetch(ajaxurl, {
			method: 'POST',
			body: new URLSearchParams({
				action:      'edp_save_post_mapping',
				nonce:       nonces.mapPost,
				location_id: locationId,
				post_id:     postId,
			}),
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					input.classList.remove('edp-input--error');
					var clearBtn = document.querySelector('.edp-map-clear-btn[data-location-id="' + locationId + '"]');
					if (clearBtn) { clearBtn.style.display = 'inline-flex'; }
				} else {
					input.classList.add('edp-input--error');
				}
			})
			.catch(function () {
				input.classList.add('edp-input--error');
			});
	}

	document.querySelectorAll('.edp-map-post-input').forEach(function (input) {
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				input._skipBlur = true;
				saveMapPost(input);
				setTimeout(function () { input._skipBlur = false; }, 300);
			}
		});
		input.addEventListener('blur', function () {
			if (!input._skipBlur) {
				saveMapPost(input);
			}
		});
		input.addEventListener('input', function () {
			input.classList.remove('edp-input--error');
		});
	});

	/* ── Static Page — Create button (AJAX, no nested form) ─ */
	function attachClearBtn(btn) {
		btn.addEventListener('click', function () {
			var locationId = this.dataset.locationId;

			// eslint-disable-next-line no-alert
			if (!confirm(confirmClear)) {
				return;
			}

			btn.disabled = true;

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_clear_override',
					nonce:       nonces.clearOverride,
					location_id: locationId,
					delete_post:  '1',
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						window.location.reload();
					} else {
						btn.disabled = false;
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					btn.disabled = false;
				});
		});
	}

	document.querySelectorAll('.edp-create-page-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var locationId = this.dataset.locationId;
			var cell       = this.closest('td');
			btn.disabled   = true;

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_create_location_page',
					nonce:       nonces.createPage,
					location_id: locationId,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						cell.innerHTML = json.data.html;
						cell.querySelectorAll('.edp-clear-cpt-btn').forEach(attachClearBtn);
					} else {
						btn.disabled = false;
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					btn.disabled = false;
				});
		});
	});

	/* ── Static Page — wire up any trash icons already on the page ── */
	document.querySelectorAll('.edp-clear-cpt-btn').forEach(attachClearBtn);

	/* ── Row delete (City column row action) ─────────── */
	document.querySelectorAll('.edp-row-delete-btn').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var locationId = this.dataset.locationId;

			// eslint-disable-next-line no-alert
			if (!confirm(confirmDeleteRow)) {
				return;
			}

			var row = btn.closest('tr');
			btn.style.pointerEvents = 'none';
			btn.style.opacity = '0.5';

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_delete_location_row',
					nonce:       nonces.deleteRow,
					location_id: locationId,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						if (row) {
							row.style.transition = 'opacity .3s';
							row.style.opacity    = '0';
							setTimeout(function () { row.remove(); }, 320);
						}
					} else {
						btn.style.pointerEvents = '';
						btn.style.opacity       = '';
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					btn.style.pointerEvents = '';
					btn.style.opacity       = '';
				});
		});
	});

	/* ── Delete All Rows button ─────────────────────── */
	var deleteAllBtn = document.getElementById('edp-delete-all-btn');
	if (deleteAllBtn) {
		deleteAllBtn.addEventListener('click', function () {
			// eslint-disable-next-line no-alert
			if (!confirm(<?php echo wp_json_encode(__('Delete ALL location rows and their Google Places data? This cannot be undone.', 'emergencydentalpros')); ?>)) {
				return;
			}

			deleteAllBtn.disabled = true;
			deleteAllBtn.style.opacity = '0.5';

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action: 'edp_delete_all_rows',
					nonce:  deleteAllBtn.dataset.nonce,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						window.location.href = <?php echo wp_json_encode(admin_url('admin.php?page=edp-seo-locations&rows_deleted=')); ?> + (json.data.deleted || 0);
					} else {
						deleteAllBtn.disabled = false;
						deleteAllBtn.style.opacity = '';
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					deleteAllBtn.disabled = false;
					deleteAllBtn.style.opacity = '';
				});
		});
	}

	/* ── Column filter / sort UI ─────────────────────── */
	(function () {
		var filterData = <?php echo wp_json_encode([
			'states'       => EDP_Database::get_distinct_states(),
			'currentState' => $edp_state_filter,
			'currentCity'  => $edp_city_filter,
			'baseUrl'      => admin_url('admin.php?page=edp-seo-locations'),
		]); ?>;

		var openPopover = null;

		function buildUrl(extra) {
			var params = new URLSearchParams(window.location.search);
			params.delete('paged');
			Object.keys(extra).forEach(function (k) {
				if (extra[k] !== '' && extra[k] !== null && extra[k] !== undefined) {
					params.set(k, extra[k]);
				} else {
					params.delete(k);
				}
			});
			return filterData.baseUrl + '&' + params.toString();
		}

		function closePopover() {
			if (openPopover) { openPopover.remove(); openPopover = null; }
		}

		function positionPopover(pop, btn) {
			var rect = btn.getBoundingClientRect();
			var left = rect.left;
			var top  = rect.bottom + 4;
			// Keep inside viewport horizontally.
			var maxLeft = window.innerWidth - 310;
			if (left > maxLeft) { left = maxLeft; }
			pop.style.left = left + 'px';
			pop.style.top  = top  + 'px';
		}

		function makeFilterBtn(isActive) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'edp-col-filter-btn' + (isActive ? ' edp-col-filter-btn--active' : '');
			btn.title = isActive ? 'Filter active — click to change' : 'Filter';
			btn.innerHTML = '<span class="dashicons dashicons-filter"></span>';
			return btn;
		}

		/* STATE filter */
		function openStatePopover(btn) {
			closePopover();
			var pop = document.createElement('div');
			pop.className = 'edp-filter-popover';

			var clearHtml = filterData.currentState
				? '<a href="' + buildUrl({ state_filter: '', city_filter: '' }) + '" class="edp-filter-clear-link">Clear state filter</a>'
				: '';

			pop.innerHTML =
				'<div class="edp-filter-popover-header">Filter by State</div>' +
				'<div class="edp-filter-popover-search">' +
					'<input type="text" class="edp-filter-search-input" placeholder="Type to search\u2026" />' +
				'</div>' +
				'<ul class="edp-filter-options-list"></ul>' +
				clearHtml;

			var list        = pop.querySelector('.edp-filter-options-list');
			var searchInput = pop.querySelector('.edp-filter-search-input');

			function renderList(q) {
				var filtered = q
					? filterData.states.filter(function (s) {
						return s.state_name.toLowerCase().indexOf(q.toLowerCase()) === 0 ||
							s.state_id.toLowerCase() === q.toLowerCase();
					})
					: filterData.states;

				if (!filtered.length) {
					list.innerHTML = '<li class="edp-filter-options-empty">No states found</li>';
					return;
				}

				list.innerHTML = filtered.map(function (s) {
					var active = filterData.currentState === s.state_slug ? ' edp-filter-option--active' : '';
					return '<li class="edp-filter-option' + active + '">' +
						'<a href="' + buildUrl({ state_filter: s.state_slug, city_filter: '', paged: '' }) + '">' +
						escHtml(s.state_name) + ' (' + escHtml(s.state_id.toUpperCase()) + ')' +
						'</a></li>';
				}).join('');
			}

			renderList('');
			searchInput.addEventListener('input', function () { renderList(this.value); });

			document.body.appendChild(pop);
			positionPopover(pop, btn);
			openPopover = pop;
			searchInput.focus();
		}

		/* CITY filter */
		function openCityPopover(btn) {
			closePopover();
			var pop = document.createElement('div');
			pop.className = 'edp-filter-popover';

			var clearHtml = filterData.currentCity
				? '<a href="' + buildUrl({ city_filter: '' }) + '" class="edp-filter-clear-link">Clear city filter</a>'
				: '';

			pop.innerHTML =
				'<div class="edp-filter-popover-header">Filter by City</div>' +
				'<div class="edp-filter-popover-search">' +
					'<input type="text" class="edp-filter-search-input" placeholder="City name\u2026" value="' + escAttr(filterData.currentCity) + '" />' +
					'<button type="button" class="edp-filter-apply-btn">Apply</button>' +
				'</div>' +
				clearHtml;

			var input    = pop.querySelector('.edp-filter-search-input');
			var applyBtn = pop.querySelector('.edp-filter-apply-btn');

			function apply() {
				var val = input.value.trim();
				window.location.href = buildUrl({ city_filter: val, paged: '' });
			}

			applyBtn.addEventListener('click', apply);
			input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { apply(); } });

			document.body.appendChild(pop);
			positionPopover(pop, btn);
			openPopover = pop;
			input.focus();
			if (input.value) { input.select(); }
		}

		/* Utility: escape HTML for inline strings */
		function escHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}
		function escAttr(str) { return escHtml(str); }

		/* Inject filter buttons into column headers */
		function initFilterButtons() {
			var thead = document.querySelector('#edp-locations-wrap table.wp-list-table thead tr');
			if (!thead) { return; }

			// State column
			var thState = thead.querySelector('.column-state');
			if (thState) {
				var stateBtn = makeFilterBtn(!!filterData.currentState);
				stateBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					if (openPopover) { closePopover(); return; }
					openStatePopover(stateBtn);
				});
				thState.appendChild(stateBtn);
			}

			// City column
			var thCity = thead.querySelector('.column-city');
			if (thCity) {
				var cityBtn = makeFilterBtn(!!filterData.currentCity);
				cityBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					if (openPopover) { closePopover(); return; }
					openCityPopover(cityBtn);
				});
				thCity.appendChild(cityBtn);
			}
		}

		/* Close popover when clicking outside */
		document.addEventListener('click', function (e) {
			if (openPopover && !openPopover.contains(e.target)) {
				closePopover();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closePopover(); }
		});

		initFilterButtons();
	}());

	/* ── Map Post — clear (✕) button ─────────────────── */
	document.querySelectorAll('.edp-map-clear-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var locationId = this.dataset.locationId;
			var wrap       = this.closest('.edp-map-post-wrap');
			var input      = wrap ? wrap.querySelector('.edp-map-post-input') : null;

			btn.disabled = true;

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_clear_override',
					nonce:       nonces.clearOverride,
					location_id: locationId,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						if (input) {
							input.value = '';
							input.classList.remove('edp-input--error');
						}
						btn.style.display = 'none';
					} else {
						btn.disabled = false;
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || errMsg);
					}
				})
				.catch(function () {
					btn.disabled = false;
				});
		});
	});
})();
</script>
