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
	<?php if (isset($_GET['cqs_analyzed'])) : ?>
		<div class="edp-notice edp-notice-success">
			<?php printf(
				/* translators: %d: number of analyzed locations */
				esc_html__('Content quality score computed for %d location(s).', 'emergencydentalpros'),
				(int) $_GET['cqs_analyzed']
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

	/* ── SEO column ──────────────────────────────────── */
	(function () {
		var metricLabels = { lcp: 'LCP', tbt: 'TBT', cls: 'CLS', fcp: 'FCP', si: 'Speed Index' };
		var statusLabels = { ok: 'Good', attention: 'Needs Improvement', crucial: 'Poor' };
		var openSeoPopover = null;
		var seoPopoverTarget = null;

		function seoStatus(score) {
			if (score >= 90) { return 'ok'; }
			if (score >= 50) { return 'attention'; }
			return 'crucial';
		}

		function buildSeoPopover(indicator) {
			var mScore   = parseInt(indicator.dataset.mobileScore,  10) || 0;
			var dScore   = parseInt(indicator.dataset.desktopScore, 10) || 0;
			var mMet     = safeJson(indicator.dataset.mobileMetrics);
			var dMet     = safeJson(indicator.dataset.desktopMetrics);
			var checkedAt = indicator.dataset.checkedAt || '';
			var activeTab = 'mobile';

			var pop = document.createElement('div');
			pop.className = 'edp-seo-popover';

			function renderBody() {
				var score   = activeTab === 'mobile' ? mScore   : dScore;
				var metrics = activeTab === 'mobile' ? mMet     : dMet;
				var status  = seoStatus(score);
				var label   = statusLabels[status] || '';
				var grid    = '';

				Object.keys(metricLabels).forEach(function (k) {
					grid += '<div class="edp-seo-metric">'
						+ '<span class="edp-metric-label">' + metricLabels[k] + '</span>'
						+ '<span class="edp-metric-value">' + escHtmlStr(metrics[k] || '\u2014') + '</span>'
						+ '</div>';
				});

				return '<div class="edp-seo-popover-body">'
					+ '<div class="edp-seo-score-row">'
					+ '<span class="edp-seo-score-big edp-seo--' + status + '">' + score + '</span>'
					+ '<span class="edp-seo-score-label"><strong>' + escHtmlStr(label) + '</strong>Performance</span>'
					+ '</div>'
					+ '<div class="edp-seo-metrics-grid">' + grid + '</div>'
					+ '</div>'
					+ (checkedAt ? '<div class="edp-seo-checked-at">Checked: ' + escHtmlStr(checkedAt) + '</div>' : '');
			}

			function render() {
				pop.innerHTML =
					'<div class="edp-seo-popover-tabs">'
					+ '<button class="edp-seo-tab' + (activeTab === 'mobile' ? ' edp-seo-tab--active' : '') + '" data-tab="mobile">Mobile</button>'
					+ '<button class="edp-seo-tab' + (activeTab === 'desktop' ? ' edp-seo-tab--active' : '') + '" data-tab="desktop">Desktop</button>'
					+ '</div>'
					+ renderBody();

				pop.querySelectorAll('.edp-seo-tab').forEach(function (btn) {
					btn.addEventListener('click', function () {
						activeTab = this.dataset.tab;
						render();
					});
				});
			}

			render();
			return pop;
		}

		function safeJson(str) {
			try { return JSON.parse(str || '{}'); } catch (e) { return {}; }
		}

		function escHtmlStr(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function positionSeoPopover(pop, indicator) {
			var rect = indicator.getBoundingClientRect();
			var left = rect.left;
			var top  = rect.bottom + 6;
			if (left + 260 > window.innerWidth - 8) { left = window.innerWidth - 268; }
			pop.style.left = left + 'px';
			pop.style.top  = top  + 'px';
		}

		function closeSeoPopover() {
			if (openSeoPopover) { openSeoPopover.remove(); openSeoPopover = null; seoPopoverTarget = null; }
		}

		function attachSeoIndicator(indicator) {
			var leaveTimer;

			indicator.addEventListener('mouseenter', function () {
				clearTimeout(leaveTimer);
				if (openSeoPopover && seoPopoverTarget === indicator) { return; }
				closeSeoPopover();
				var pop = buildSeoPopover(indicator);
				document.body.appendChild(pop);
				positionSeoPopover(pop, indicator);
				openSeoPopover    = pop;
				seoPopoverTarget  = indicator;

				pop.addEventListener('mouseenter', function () { clearTimeout(leaveTimer); });
				pop.addEventListener('mouseleave', function () {
					leaveTimer = setTimeout(closeSeoPopover, 200);
				});
			});

			indicator.addEventListener('mouseleave', function () {
				leaveTimer = setTimeout(closeSeoPopover, 200);
			});
		}

		function showSeoProgress(cell) {
			cell.innerHTML =
				'<div class="edp-seo-progress-wrap">'
				+ '<span class="edp-seo-progress-label">Checking SEO\u2026</span>'
				+ '<div class="edp-seo-progress-track">'
				+ '<div class="edp-seo-progress-fill"></div>'
				+ '</div>'
				+ '</div>';

			var fill   = cell.querySelector('.edp-seo-progress-fill');
			var timers = [];

			// Simulate two-stage progress: mobile call → desktop call.
			[[100, 6], [3500, 38], [9000, 65], [16000, 84], [23000, 92]].forEach(function (s) {
				timers.push(setTimeout(function () {
					if (fill && fill.parentNode) { fill.style.width = s[1] + '%'; }
				}, s[0]));
			});

			return {
				complete: function (onDone) {
					timers.forEach(clearTimeout);
					if (fill && fill.parentNode) {
						fill.classList.add('edp-progress--done');
						fill.style.width = '100%';
						setTimeout(onDone, 350);
					} else {
						onDone();
					}
				},
				cancel: function () {
					timers.forEach(clearTimeout);
				},
			};
		}

		function runSeoCheck(locationId, nonce, cell, original) {
			var progress = showSeoProgress(cell);

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_check_pagespeed',
					nonce:       nonce,
					location_id: locationId,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						progress.complete(function () {
							cell.innerHTML = json.data.html;
							cell.querySelectorAll('.edp-seo-indicator').forEach(attachSeoIndicator);
							cell.querySelectorAll('.edp-recheck-seo-btn').forEach(attachRecheckBtn);
						});
					} else {
						progress.cancel();
						cell.innerHTML = original;
						var msg = (json.data && json.data.message) || errMsg;
						if (json.data && json.data.debug) {
							msg += '\n\nDebug:\nURL: '         + json.data.debug.url
								+ '\nKey prefix: ' + json.data.debug.key_prefix
								+ '\nStrategy: '  + json.data.debug.strategy;
						}
						// eslint-disable-next-line no-alert
						alert(msg);
					}
				})
				.catch(function () {
					progress.cancel();
					cell.innerHTML = original;
				});
		}

		function attachSeoCheckBtn(btn) {
			btn.addEventListener('click', function () {
				var cell = this.closest('td');
				runSeoCheck(this.dataset.locationId, this.dataset.nonce, cell, cell.innerHTML);
			});
		}

		function attachRecheckBtn(btn) {
			btn.addEventListener('click', function () {
				closeSeoPopover();
				var cell = this.closest('td');
				runSeoCheck(this.dataset.locationId, this.dataset.nonce, cell, cell.innerHTML);
			});
		}

		document.querySelectorAll('.edp-seo-indicator').forEach(attachSeoIndicator);
		document.querySelectorAll('.edp-check-seo-btn').forEach(attachSeoCheckBtn);
		document.querySelectorAll('.edp-recheck-seo-btn').forEach(attachRecheckBtn);
	}());

	/* ── CQS column ─────────────────────────────────────── */
	(function () {
		var CATS = <?php echo wp_json_encode(
			array_map(
				static function ( array $def ): array {
					return [ 'name' => $def['name'], 'color' => $def['color'], 'max' => $def['max'] ];
				},
				EDP_Cqs_Scorer::CATEGORIES
			)
		); ?>;

		var CAT_KEYS = <?php echo wp_json_encode( array_keys( EDP_Cqs_Scorer::CATEGORIES ) ); ?>;

		var GRADE_LABELS = {
			perfect: 'Perfect',
			great:   'Great',
			good:    'Good',
			average: 'Average',
			poor:    'Needs Work',
		};

		var openCqsPopover   = null;
		var cqsPopoverTarget = null;

		function cqsGrade(score) {
			if (score >= 95) { return 'perfect'; }
			if (score >= 85) { return 'great'; }
			if (score >= 75) { return 'good'; }
			if (score >= 50) { return 'average'; }
			return 'poor';
		}

		function escHtml(s) {
			return String(s)
				.replace(/&/g,'&amp;').replace(/</g,'&lt;')
				.replace(/>/g,'&gt;').replace(/"/g,'&quot;');
		}

		function buildCqsPopover(indicator) {
			var score      = parseInt(indicator.dataset.score, 10) || 0;
			var grade      = indicator.dataset.grade || cqsGrade(score);
			var analyzedAt = indicator.dataset.analyzedAt || '';
			var breakdown  = {};
			try { breakdown = JSON.parse(indicator.dataset.breakdown || '{}'); } catch(e) {}

			var pop = document.createElement('div');
			pop.className = 'edp-cqs-popover';

			var gradeLabel = GRADE_LABELS[grade] || '';
			var scoreColor = {
				perfect:'#7c3aed', great:'#3b82f6', good:'#10b981',
				average:'#f59e0b', poor:'#ef4444'
			}[grade] || '#888';

			var catsHtml = '';
			CAT_KEYS.forEach(function (key, i) {
				var cat  = CATS[i];
				var data = breakdown[key] || { earned: 0, max: cat.max, checks: [] };
				var pct  = cat.max > 0 ? Math.round((data.earned / cat.max) * 100) : 0;
				var checksHtml = '';
				(data.checks || []).forEach(function (c) {
					checksHtml +=
						'<div class="edp-cqs-check' + (c.pass ? ' edp-cqs-check--pass' : '') + '">'
						+ '<span class="edp-cqs-check-icon">' + (c.pass ? '✓' : '○') + '</span>'
						+ '<span class="edp-cqs-check-text">' + escHtml(c.text) + '</span>'
						+ '<span class="edp-cqs-check-pts">+' + c.pts + '</span>'
						+ '</div>';
				});
				catsHtml +=
					'<div class="edp-cqs-cat">'
					+ '<div class="edp-cqs-cat-header">'
					+ '<span class="edp-cqs-cat-dot" style="background:' + cat.color + '"></span>'
					+ '<span class="edp-cqs-cat-name">' + escHtml(cat.name) + '</span>'
					+ '<span class="edp-cqs-cat-pts">' + data.earned + '/' + cat.max + '</span>'
					+ '<div class="edp-cqs-cat-bar"><div class="edp-cqs-cat-bar-fill" style="width:' + pct + '%;background:' + cat.color + '"></div></div>'
					+ '</div>'
					+ (checksHtml ? '<div class="edp-cqs-checks">' + checksHtml + '</div>' : '')
					+ '</div>';
			});

			pop.innerHTML =
				'<div class="edp-cqs-popover-head">'
				+ '<span class="edp-cqs-score-big" style="color:' + scoreColor + '">' + score + '</span>'
				+ '<div class="edp-cqs-score-meta">'
				+ '<strong>' + escHtml(gradeLabel) + '</strong>'
				+ '<span>Content Quality Score</span>'
				+ '</div>'
				+ '</div>'
				+ '<div class="edp-cqs-cats">' + catsHtml + '</div>'
				+ (analyzedAt ? '<div class="edp-cqs-footer">Analyzed: ' + escHtml(analyzedAt) + '</div>' : '');

			return pop;
		}

		function positionCqsPopover(pop, indicator) {
			var rect = indicator.getBoundingClientRect();
			var left = rect.left;
			var top  = rect.bottom + 6;
			if (left + 300 > window.innerWidth - 8) { left = window.innerWidth - 308; }
			pop.style.left = left + 'px';
			pop.style.top  = top  + 'px';
		}

		function closeCqsPopover() {
			if (openCqsPopover) { openCqsPopover.remove(); openCqsPopover = null; cqsPopoverTarget = null; }
		}

		function attachCqsIndicator(indicator) {
			var leaveTimer;
			indicator.addEventListener('mouseenter', function () {
				clearTimeout(leaveTimer);
				if (openCqsPopover && cqsPopoverTarget === indicator) { return; }
				closeCqsPopover();
				var pop = buildCqsPopover(indicator);
				document.body.appendChild(pop);
				positionCqsPopover(pop, indicator);
				openCqsPopover   = pop;
				cqsPopoverTarget = indicator;
				pop.addEventListener('mouseenter', function () { clearTimeout(leaveTimer); });
				pop.addEventListener('mouseleave', function () { leaveTimer = setTimeout(closeCqsPopover, 200); });
			});
			indicator.addEventListener('mouseleave', function () {
				leaveTimer = setTimeout(closeCqsPopover, 200);
			});
		}

		function runCqsAnalyze(locationId, nonce, cell) {
			var original = cell.innerHTML;
			cell.innerHTML = '<span class="edp-cqs-analyzing">Analyzing\u2026</span>';

			fetch(ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action:      'edp_analyze_cqs',
					nonce:       nonce,
					location_id: locationId,
				}),
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						cell.innerHTML = json.data.html;
						cell.querySelectorAll('.edp-cqs-indicator').forEach(attachCqsIndicator);
						cell.querySelectorAll('.edp-reanalyze-cqs-btn').forEach(attachReanalyzeCqsBtn);
					} else {
						cell.innerHTML = original;
						// eslint-disable-next-line no-alert
						alert((json.data && json.data.message) || <?php echo wp_json_encode(__('Analysis failed.', 'emergencydentalpros')); ?>);
					}
				})
				.catch(function () { cell.innerHTML = original; });
		}

		function attachAnalyzeCqsBtn(btn) {
			btn.addEventListener('click', function () {
				runCqsAnalyze(this.dataset.locationId, this.dataset.nonce, this.closest('td'));
			});
		}

		function attachReanalyzeCqsBtn(btn) {
			btn.addEventListener('click', function () {
				closeCqsPopover();
				runCqsAnalyze(this.dataset.locationId, this.dataset.nonce, this.closest('td'));
			});
		}

		document.querySelectorAll('.edp-cqs-indicator').forEach(attachCqsIndicator);
		document.querySelectorAll('.edp-analyze-cqs-btn').forEach(attachAnalyzeCqsBtn);
		document.querySelectorAll('.edp-reanalyze-cqs-btn').forEach(attachReanalyzeCqsBtn);

		document.addEventListener('click', function (e) {
			if (openCqsPopover && !openCqsPopover.contains(e.target) && !e.target.closest('.edp-cqs-indicator')) {
				closeCqsPopover();
			}
		});
	}());

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
