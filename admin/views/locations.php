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

/* ── Page shell ───────────────────────────────────────────── */
#edp-locations-wrap {
	font-family: 'Lato', sans-serif;
	color: #3A3541;
}
#edp-locations-wrap h1 {
	font-family: 'Lato', sans-serif;
	font-weight: 700;
	font-size: 25.63px;
	color: #3A3541;
	margin-bottom: 4px;
}
#edp-locations-wrap .edp-subtitle {
	font-size: 14.22px;
	color: #89868D;
	margin-top: 0;
	margin-bottom: 24px;
}

/* ── Notices ──────────────────────────────────────────────── */
.edp-notice {
	border-radius: 8px;
	padding: 12px 16px;
	margin-bottom: 16px;
	font-size: 14.22px;
	font-family: 'Lato', sans-serif;
}
.edp-notice ul { margin: 6px 0 0; padding-left: 1.25em; list-style: disc; }
.edp-notice-success { background: #f0faf4; border-left: 4px solid #2ecc71; color: #0a7040; }
.edp-notice-error   { background: #fff5f5; border-left: 4px solid #e74c3c; color: #c0392b; }
.edp-notice-warning { background: #fff3cd; border-left: 4px solid #f0b429; color: #7a5200; }
.edp-notice-info    { background: #f0f6fc; border-left: 4px solid #2271b1; color: #1a4a7a; }

/* ── Stat cards row ───────────────────────────────────────── */
.edp-stat-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 24px;
}
.edp-stat-card {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
	padding: 20px;
	font-family: 'Lato', sans-serif;
}
.edp-stat-card-title {
	font-weight: 700;
	font-size: 25.63px;
	color: #3A3541;
	margin: 0 0 4px;
	line-height: 1;
}
.edp-stat-card-sub {
	font-size: 14.22px;
	color: #89868D;
	margin: 0 0 16px;
}
.edp-stat-row-items {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.edp-stat-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	font-size: 14.22px;
	color: #3A3541;
}
.edp-stat-item .dashicons {
	flex-shrink: 0;
	font-size: 16px;
	width: 16px;
	height: 16px;
	margin-top: 2px;
}
.edp-stat-item .dashicons-yes  { color: #0a7040; }
.edp-stat-item .dashicons-warning { color: #b32d2e; }
.edp-stat-item .dashicons-info  { color: #2271b1; }
.edp-stat-label { color: #89868D; }
.edp-stat-val   { color: #3A3541; font-weight: 500; }
.edp-stat-err   { color: #b32d2e; font-size: 12.64px; margin-top: 2px; }
.edp-stat-card-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 16px;
	padding-top: 14px;
	border-top: 1px solid #DBDCDE;
}

/* ── Buttons ──────────────────────────────────────────────── */
.edp-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: 4px;
	height: 32px;
	padding: 0 16px;
	font-size: 12.64px;
	font-family: 'Lato', sans-serif;
	cursor: pointer;
	text-decoration: none;
	transition: opacity 0.15s;
	line-height: 1;
}
.edp-btn:hover { opacity: 0.88; text-decoration: none; }
.edp-btn-primary   { background: #6E39CB; color: #fff !important; }
.edp-btn-secondary { background: #fff; color: #3A3541 !important; box-shadow: 0 0 4px rgba(0,0,0,0.15); }

/* ── Diagnostics card ─────────────────────────────────────── */
.edp-diag-card {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
	margin-bottom: 24px;
	overflow: hidden;
}
.edp-diag-header {
	background: #fff3cd;
	padding: 12px 20px;
	font-weight: 500;
	font-size: 14.22px;
	color: #7a5200;
	display: flex;
	align-items: center;
	gap: 8px;
}
.edp-diag-body {
	padding: 16px 20px;
}
.edp-diag-body p { font-size: 13px; color: #89868D; margin: 0 0 8px; }
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

/* ── Table card ───────────────────────────────────────────── */
.edp-table-card {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
	overflow: hidden;
}
.edp-table-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	padding: 20px 20px 0;
}
.edp-table-header-text {}
.edp-table-header-text h2 {
	font-weight: 700;
	font-size: 25.63px;
	color: #3A3541;
	margin: 0 0 4px;
	padding: 0;
	line-height: 1;
	border: none;
}
.edp-table-header-text p {
	font-size: 14.22px;
	color: #89868D;
	margin: 0;
}

/* WP List Table overrides — matches Figma table style */
#edp-locations-wrap .tablenav {
	padding: 10px 20px;
	display: flex;
	align-items: center;
}
#edp-locations-wrap .tablenav.top {
	border-bottom: 1px solid #DBDCDE;
}
#edp-locations-wrap .tablenav.bottom {
	border-top: 1px solid #DBDCDE;
}
#edp-locations-wrap .tablenav .tablenav-pages {
	font-family: 'Lato', sans-serif;
	font-size: 13px;
	color: #89868D;
}
#edp-locations-wrap .tablenav .button {
	font-family: 'Lato', sans-serif;
	border-radius: 4px;
}
#edp-locations-wrap .search-box input[type="search"] {
	background: #F4F5F9;
	border: 1px solid #DBDCDE;
	border-radius: 8px;
	padding: 6px 12px;
	font-family: 'Lato', sans-serif;
	font-size: 14.22px;
	color: #3A3541;
}
#edp-locations-wrap .search-box input[type="search"]:focus {
	outline: none;
	border-color: #6E39CB;
	box-shadow: 0 0 0 2px rgba(110,57,203,0.15);
}
#edp-locations-wrap .search-box .button {
	background: #6E39CB;
	color: #fff;
	border-color: #6E39CB;
	border-radius: 4px;
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
	background: #fff;
	font-family: 'Lato', sans-serif;
	font-weight: 500;
	font-size: 16px;
	color: #3A3541;
	border-bottom: 1px solid #DBDCDE;
	border-top: none;
	padding: 14px 20px;
	white-space: nowrap;
}
#edp-locations-wrap table.wp-list-table thead th a,
#edp-locations-wrap table.wp-list-table thead th a:hover {
	color: #3A3541;
	font-weight: 500;
}
#edp-locations-wrap table.wp-list-table thead th.sorted,
#edp-locations-wrap table.wp-list-table thead th.asc,
#edp-locations-wrap table.wp-list-table thead th.desc {
	background: #F4F5F9;
}
#edp-locations-wrap table.wp-list-table tbody tr {
	border-bottom: 1px solid #F4F5F9;
	background: #fff;
	transition: background 0.1s;
}
#edp-locations-wrap table.wp-list-table tbody tr:hover {
	background: #fafafa;
}
#edp-locations-wrap table.wp-list-table tbody tr.alternate {
	background: #fff;
}
#edp-locations-wrap table.wp-list-table tbody td,
#edp-locations-wrap table.wp-list-table tbody th {
	border-bottom: 1px solid #F4F5F9;
	border-top: none;
	padding: 14px 20px;
	font-size: 14.22px;
	color: #89868D;
	vertical-align: middle;
	font-family: 'Lato', sans-serif;
}
#edp-locations-wrap table.wp-list-table tbody td.column-primary,
#edp-locations-wrap table.wp-list-table tbody th.column-primary {
	color: #3A3541;
	font-weight: 500;
}
#edp-locations-wrap table.wp-list-table .row-actions {
	font-size: 12.64px;
	color: #89868D;
}
#edp-locations-wrap table.wp-list-table .row-actions a {
	color: #6E39CB;
}
#edp-locations-wrap table.wp-list-table .row-actions .delete a,
#edp-locations-wrap table.wp-list-table .row-actions .trash a {
	color: #b32d2e;
}

/* Existing listing-cell badges — keep functional, refine visuals */
#edp-locations-wrap .edp-listing-cell { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
#edp-locations-wrap .edp-listing-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 13px; line-height: 1; font-family: 'Lato', sans-serif; }
#edp-locations-wrap .edp-listing-badge--has   { color: #0a7040; font-weight: 600; }
#edp-locations-wrap .edp-listing-badge--empty { color: #89868D; }
#edp-locations-wrap .edp-listing-badge .dashicons { font-size: 15px; width: 15px; height: 15px; }
#edp-locations-wrap .edp-listing-btns { display: inline-flex; gap: 4px; }
#edp-locations-wrap .edp-listing-btn {
	background: #fff !important;
	border: 1px solid #DBDCDE !important;
	border-radius: 4px !important;
	color: #3A3541 !important;
	padding: 3px 8px !important;
	min-height: 26px !important;
	line-height: 1 !important;
	display: inline-flex !important;
	align-items: center !important;
	gap: 3px;
	font-size: 12px !important;
	font-family: 'Lato', sans-serif !important;
	cursor: pointer;
	transition: background 0.12s, border-color 0.12s;
}
#edp-locations-wrap .edp-listing-btn:hover {
	background: #F4F5F9 !important;
	border-color: #6E39CB !important;
	color: #6E39CB !important;
}
#edp-locations-wrap .edp-listing-btn .dashicons { font-size: 13px; width: 13px; height: 13px; }
#edp-locations-wrap .edp-listing-btn--danger { color: #b32d2e !important; border-color: #b32d2e !important; }
#edp-locations-wrap .edp-listing-btn--danger:hover { background: #fff5f5 !important; }
#edp-locations-wrap .edp-listing-cell--loading { opacity: 0.45; pointer-events: none; }
</style>

<div id="edp-locations-wrap" class="wrap">
	<h1><?php esc_html_e('Local SEO — Locations', 'emergencydentalpros'); ?></h1>
	<p class="edp-subtitle"><?php esc_html_e('Map locations to posts, create CPT overrides, or run Google Places fetch per city.', 'emergencydentalpros'); ?></p>

	<?php /* ── Flash notices ── */ ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['google_none'])) : ?>
		<div class="edp-notice edp-notice-warning"><?php esc_html_e('No locations were selected.', 'emergencydentalpros'); ?></div>
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
									'file_not_readable'      => __('CSV could not be read (wrong path or permissions).', 'emergencydentalpros'),
									'fopen_failed'           => __('Could not open CSV file.', 'emergencydentalpros'),
									'empty_or_invalid_csv'   => __('CSV was empty or invalid.', 'emergencydentalpros'),
									'missing_columns'        => __('CSV is missing required columns (zip, city, state_id, state_name).', 'emergencydentalpros'),
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
	<div class="edp-table-card">
		<div class="edp-table-header">
			<div class="edp-table-header-text">
				<h2><?php esc_html_e('Locations', 'emergencydentalpros'); ?></h2>
				<p><?php esc_html_e('Map a location to an existing post/page ID, create a CPT override from global templates, or clear overrides.', 'emergencydentalpros'); ?></p>
			</div>
		</div>
		<?php $table->display(); ?>
	</div>
</div>

<script>
(function () {
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
						alert((json.data && json.data.message) || <?php echo wp_json_encode(__('An error occurred.', 'emergencydentalpros')); ?>);
					}
				})
				.catch(function () {
					cell.innerHTML = original;
				});
		});
	}

	document.querySelectorAll('.edp-listing-btn').forEach(attachListingBtn);
})();
</script>
