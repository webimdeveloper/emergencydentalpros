<?php
/**
 * CSV / Google Sheets import screen.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
	exit;
}

$persisted = get_option(EDP_Admin::OPTION_IMPORT_LOG, []);

if (!is_array($persisted)) {
	$persisted = [];
}

$saved_sheet_url  = (string) get_option(EDP_Admin::OPTION_SHEET_URL, '');
$sa_configured    = EDP_Sheet_Credentials::is_configured();
$sa_email         = $sa_configured ? EDP_Sheet_Credentials::get_client_email() : '';

$google = EDP_Google_Places_Config::get_all();
$google_key_set = EDP_Google_Places_Config::get_api_key() !== '';

$uid = get_current_user_id();
$google_test_result = get_transient('edp_seo_google_test_' . $uid);

if (is_array($google_test_result)) {
	delete_transient('edp_seo_google_test_' . $uid);
} else {
	$google_test_result = null;
}

?>

<div id="edp-import-wrap" class="wrap">
	<h1><?php esc_html_e('Local SEO — Import', 'emergencydentalpros'); ?></h1>
	<p class="edp-subtitle"><?php esc_html_e('Manage Google Sheets sync and Google Places data import.', 'emergencydentalpros'); ?></p>

	<?php /* ---- Flash notices ---- */ ?>

	<?php if ($google_test_result !== null) : ?>
		<div class="edp-notice <?php echo !empty($google_test_result['ok']) ? 'edp-notice-success' : 'edp-notice-error'; ?>">
			<div>
				<strong><?php esc_html_e('Google Places API test', 'emergencydentalpros'); ?></strong><br />
				<?php echo esc_html((string) ($google_test_result['message'] ?? '')); ?>
				<?php if (isset($google_test_result['api_calls'])) : ?>
					<br /><span style="font-size:12px;">
					<?php
					printf(
						/* translators: %d: number of API calls */
						esc_html__('API calls used: %d', 'emergencydentalpros'),
						(int) $google_test_result['api_calls']
					);
					?>
					</span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['sheet_saved'])) : ?>
		<div class="edp-notice edp-notice-success"><?php esc_html_e('Google Sheets URL saved.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>
	<?php if (isset($_GET['google_saved'])) : ?>
		<div class="edp-notice edp-notice-success"><?php esc_html_e('Google Places settings saved.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>
	<?php if (isset($_GET['sa_saved'])) : ?>
		<div class="edp-notice edp-notice-success"><?php esc_html_e('Service account credentials saved.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>
	<?php if (isset($_GET['sa_cleared'])) : ?>
		<div class="edp-notice edp-notice-info"><?php esc_html_e('Service account credentials removed.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>
	<?php if (isset($_GET['sa_error'])) : ?>
		<div class="edp-notice edp-notice-error">
			<?php
			$sa_err_code = sanitize_key((string) wp_unslash($_GET['sa_error']));
			$sa_err_msg  = isset($_GET['sa_msg']) ? sanitize_text_field((string) wp_unslash($_GET['sa_msg'])) : '';
			if ($sa_err_code === 'parse' && $sa_err_msg !== '') {
				echo esc_html($sa_err_msg);
			} elseif ($sa_err_code === 'empty') {
				esc_html_e('The uploaded file was empty.', 'emergencydentalpros');
			} else {
				esc_html_e('Could not read the uploaded file. Make sure you selected a valid JSON key file.', 'emergencydentalpros');
			}
			?>
		</div>
	<?php endif; ?>
	<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* ROW — Service Account (left) + Plugin Documentation (right)       */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>
	<div class="edp-stat-row">

		<?php /* CARD 1 — Service Account */ ?>
		<div class="edp-card" style="margin-bottom:0;">
			<div class="edp-card-header">
				<?php esc_html_e('Google Sheets — Service Account', 'emergencydentalpros'); ?>
				<?php if ($sa_configured) : ?>
					<span class="edp-card-header-badge edp-badge-ok">&#10003; <?php esc_html_e('Connected', 'emergencydentalpros'); ?></span>
				<?php else : ?>
					<span class="edp-card-header-badge"><?php esc_html_e('Not configured', 'emergencydentalpros'); ?></span>
				<?php endif; ?>
			</div>
			<div class="edp-card-body">
				<p class="edp-desc">
					<?php esc_html_e('Required for the two-way sync. Upload the JSON key file you downloaded from Google Cloud Console after creating the service account. The sheet must be shared with the service account email as Editor.', 'emergencydentalpros'); ?>
				</p>

				<?php if ($sa_configured) : ?>
					<div class="edp-connected-row">
						<span class="dashicons dashicons-yes"></span>
						<strong><?php esc_html_e('Connected:', 'emergencydentalpros'); ?></strong>
						<code><?php echo esc_html($sa_email); ?></code>
					</div>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('edp_sheet_sa_clear', 'edp_sheet_sa_clear_nonce'); ?>
						<input type="hidden" name="action" value="edp_sheet_sa_clear" />
						<button type="submit" class="edp-btn edp-btn-danger"><?php esc_html_e('Remove credentials', 'emergencydentalpros'); ?></button>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field('edp_sheet_sa_save', 'edp_sheet_sa_nonce'); ?>
						<input type="hidden" name="action" value="edp_sheet_sa_save" />
						<div class="edp-form-row">
							<label for="edp_sa_json"><?php esc_html_e('Service account JSON key', 'emergencydentalpros'); ?></label>
							<input name="edp_sa_json" type="file" id="edp_sa_json" accept=".json,application/json" required />
							<p class="edp-hint"><?php esc_html_e('Google Cloud Console → IAM & Admin → Service Accounts → your account → Keys → Add Key → JSON.', 'emergencydentalpros'); ?></p>
						</div>
						<div class="edp-btn-row">
							<button type="submit" class="edp-btn edp-btn-primary"><?php esc_html_e('Upload & save credentials', 'emergencydentalpros'); ?></button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<?php /* Plugin Documentation */ ?>
		<div class="edp-stat-card">
			<p class="edp-stat-card-title"><?php esc_html_e('Plugin Documentation', 'emergencydentalpros'); ?></p>
			<p class="edp-stat-card-sub"><?php esc_html_e('Admin guides for managing locations and understanding the plugin architecture.', 'emergencydentalpros'); ?></p>
			<div class="edp-doc-links">
				<a href="<?php echo esc_url(admin_url('admin.php?page=edp-seo-doc&doc=guide')); ?>" class="edp-doc-link-row">
					<span class="dashicons dashicons-media-document edp-doc-link-icon" aria-hidden="true"></span>
					<div class="edp-doc-link-text">
						<strong><?php esc_html_e('User Guide', 'emergencydentalpros'); ?></strong>
						<span><?php esc_html_e('Import locations, connect APIs, create static pages, map post IDs, templates, FAQ and schema setup.', 'emergencydentalpros'); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2 edp-doc-link-arrow" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=edp-seo-doc&doc=architecture')); ?>" class="edp-doc-link-row">
					<span class="dashicons dashicons-editor-code edp-doc-link-icon edp-doc-link-icon--arch" aria-hidden="true"></span>
					<div class="edp-doc-link-text">
						<strong><?php esc_html_e('Architecture Reference', 'emergencydentalpros'); ?></strong>
						<span><?php esc_html_e('Plugin class structure, virtual routing, theme integration, AJAX actions, and how to extend the plugin.', 'emergencydentalpros'); ?></span>
					</div>
					<span class="dashicons dashicons-arrow-right-alt2 edp-doc-link-arrow" aria-hidden="true"></span>
				</a>
			</div>

			<?php /* ── Last import stat ── */ ?>
			<?php if (!empty($persisted['at'])) :
				$_imp_date    = esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $persisted['at']));
				$_imp_has_err = !empty($persisted['error']) || (isset($persisted['ok']) && !$persisted['ok']);
				$_imp_err_code = isset($persisted['error']) ? (string) $persisted['error'] : '';
				$_imp_err_msgs = [
					'file_not_readable'        => __('CSV could not be read (wrong path or permissions).', 'emergencydentalpros'),
					'fopen_failed'             => __('Could not open CSV file.', 'emergencydentalpros'),
					'empty_or_invalid_csv'     => __('CSV was empty or invalid.', 'emergencydentalpros'),
					'missing_columns'          => __('CSV is missing required columns (zip, city, state_id, state_name).', 'emergencydentalpros'),
					'custom_path_not_readable' => __('The custom CSV path was not readable.', 'emergencydentalpros'),
				];
			?>
			<div style="border-top:1px solid var(--edp-border); margin-top:16px; padding-top:14px;">
				<div class="edp-stat-item">
					<div>
						<span class="edp-stat-label"><?php esc_html_e('Last import:', 'emergencydentalpros'); ?></span>
						<span class="edp-stat-val"> <?php echo $_imp_date; ?></span>
						<br />
						<span style="font-size:12.64px; color:#89868D;">
							<?php printf(
								/* translators: 1: rows, 2: skipped, 3: groups */
								esc_html__('rows %1$d, skipped %2$d, city groups %3$d', 'emergencydentalpros'),
								(int) ($persisted['rows'] ?? 0),
								(int) ($persisted['skipped'] ?? 0),
								(int) ($persisted['groups'] ?? 0)
							); ?>
						</span>
						<?php if ($_imp_has_err) : ?>
							<br /><span class="edp-stat-err"><?php echo esc_html($_imp_err_msgs[$_imp_err_code] ?? __('Import reported an error.', 'emergencydentalpros')); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php else : ?>
			<div style="border-top:1px solid var(--edp-border); margin-top:16px; padding-top:14px;">
				<div class="edp-stat-item">
					<span class="edp-stat-label"><?php esc_html_e('No import log yet.', 'emergencydentalpros'); ?></span>
				</div>
			</div>
			<?php endif; ?>
		</div>

	</div>

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* CARD 2 — Two-Way Sync                                              */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>
	<div class="edp-card">
		<div class="edp-card-header">
			<?php esc_html_e('Google Sheets — Two-Way Sync', 'emergencydentalpros'); ?>
		</div>
		<div class="edp-card-body">
			<p class="edp-desc">
				<?php esc_html_e('Reads every row where action=TRUE, upserts city data into the database, then writes city_slug / sync_note / last_synced back to the sheet and resets action to FALSE. Requires credentials above.', 'emergencydentalpros'); ?>
			</p>
			<p class="edp-desc" style="margin-bottom:4px;"><?php esc_html_e('Required sheet columns (row 1):', 'emergencydentalpros'); ?></p>
			<code class="edp-code-block">action, status, type, google_places, faq, city, state_id, state_name, county_name, main_zip, zips, city_slug, sync_note, last_synced</code>

			<hr class="edp-divider" />

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('edp_sheet_save_url', 'edp_sheet_nonce'); ?>
				<input type="hidden" name="action" value="edp_sheet_save_url" />
				<div class="edp-form-row">
					<label for="edp_sheet_url"><?php esc_html_e('Google Sheets URL', 'emergencydentalpros'); ?></label>
					<input
						name="edp_sheet_url"
						type="url"
						id="edp_sheet_url"
						value="<?php echo esc_attr($saved_sheet_url); ?>"
						placeholder="https://docs.google.com/spreadsheets/d/…"
					/>
				</div>
				<div class="edp-btn-row">
					<button type="submit" class="edp-btn edp-btn-secondary"><?php esc_html_e('Save URL', 'emergencydentalpros'); ?></button>
				</div>
			</form>

			<?php if ($saved_sheet_url !== '') : ?>
				<div class="edp-url-display" style="margin-top:16px;">
					<span class="dashicons dashicons-yes"></span>
					<strong style="white-space:nowrap;"><?php esc_html_e('Saved URL:', 'emergencydentalpros'); ?></strong>
					<span><?php echo esc_html($saved_sheet_url); ?></span>
				</div>
			<?php endif; ?>

			<hr class="edp-divider" />

			<?php $v2_ready = $sa_configured && $saved_sheet_url !== ''; ?>
			<div style="display:flex; align-items:center; gap:12px;">
				<button
					id="edp-sheet-sync-v2-btn"
					class="edp-btn edp-btn-primary"
					<?php echo $v2_ready ? '' : 'disabled'; ?>
				><?php esc_html_e('Run Two-Way Sync', 'emergencydentalpros'); ?></button>

				<?php if (!$sa_configured) : ?>
					<span style="font-size:13px; color:#89868D;"><?php esc_html_e('Upload service account credentials first.', 'emergencydentalpros'); ?></span>
				<?php elseif ($saved_sheet_url === '') : ?>
					<span style="font-size:13px; color:#89868D;"><?php esc_html_e('Save a Sheet URL first.', 'emergencydentalpros'); ?></span>
				<?php endif; ?>
			</div>

			<div id="edp-sheet-v2-progress" class="edp-progress-wrap">
				<p id="edp-sheet-v2-status" class="edp-progress-status"></p>
				<div id="edp-sheet-v2-result" class="edp-result-box">
					<strong><?php esc_html_e('Sync complete', 'emergencydentalpros'); ?></strong>
					<ul id="edp-sheet-v2-result-list"></ul>
				</div>
				<div id="edp-sheet-v2-error" class="edp-error-box">
					<strong><?php esc_html_e('Error', 'emergencydentalpros'); ?></strong>
					<p id="edp-sheet-v2-error-msg" style="margin:4px 0 0;"></p>
				</div>
			</div>
		</div>
	</div>

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* CARD 3 — Google Places Settings                                    */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>
	<div class="edp-card">
		<div class="edp-card-header">
			<?php esc_html_e('Google Places — Settings', 'emergencydentalpros'); ?>
			<?php if ($google_key_set) : ?>
				<span class="edp-card-header-badge edp-badge-ok">&#10003; <?php esc_html_e('API key saved', 'emergencydentalpros'); ?></span>
			<?php else : ?>
				<span class="edp-card-header-badge"><?php esc_html_e('No API key', 'emergencydentalpros'); ?></span>
			<?php endif; ?>
		</div>
		<div class="edp-card-body">
			<p class="edp-desc">
				<?php esc_html_e('Fetches up to 5 dentist listings per city from the Google Places API. Requires a Google Cloud project with the Places API enabled. The $200/month free credit covers thousands of requests.', 'emergencydentalpros'); ?>
			</p>
			<p class="edp-desc"><?php esc_html_e('Setup: Google Cloud Console → APIs & Services → Enable "Places API" → Credentials → Create API Key.', 'emergencydentalpros'); ?></p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('edp_seo_save_google', 'edp_seo_google_save_nonce'); ?>
				<input type="hidden" name="action" value="edp_seo_save_google" />

				<div class="edp-form-row">
					<label for="edp_google_api_key"><?php esc_html_e('Google Places API key', 'emergencydentalpros'); ?></label>
					<input name="edp_google[api_key]" type="password" id="edp_google_api_key" autocomplete="off"
						placeholder="<?php echo $google_key_set ? esc_attr__('Leave blank to keep the saved key', 'emergencydentalpros') : esc_attr__('Paste API key from Google Cloud Console', 'emergencydentalpros'); ?>" />
					<?php if ($google_key_set) : ?>
						<p class="edp-hint"><?php esc_html_e('A key is already saved. Enter a new value only to replace it.', 'emergencydentalpros'); ?></p>
					<?php endif; ?>
				</div>

				<div class="edp-form-row-grid">
					<div class="edp-form-row" style="margin-bottom:0;">
						<label for="edp_google_term"><?php esc_html_e('Search term', 'emergencydentalpros'); ?></label>
						<input name="edp_google[term]" type="text" id="edp_google_term"
							value="<?php echo esc_attr((string) ($google['term'] ?? 'emergency dentist')); ?>" />
						<p class="edp-hint"><?php esc_html_e('Combined with city + state, e.g. "emergency dentist Birmingham AL".', 'emergencydentalpros'); ?></p>
					</div>
					<div class="edp-form-row" style="margin-bottom:0;">
						<label for="edp_google_limit"><?php esc_html_e('Max businesses per city', 'emergencydentalpros'); ?></label>
						<input name="edp_google[limit]" type="number" id="edp_google_limit" min="1" max="5" step="1"
							value="<?php echo esc_attr((string) (int) ($google['limit'] ?? 5)); ?>" />
						<p class="edp-hint"><?php esc_html_e('Maximum 5.', 'emergencydentalpros'); ?></p>
					</div>
				</div>

				<div class="edp-form-row" style="margin-top:16px;">
					<label class="edp-checkbox-label">
						<input name="edp_google[fetch_details]" type="checkbox" value="1" <?php checked(!empty($google['fetch_details'])); ?> />
						<?php esc_html_e('Fetch full hours + phone (one extra API call per business)', 'emergencydentalpros'); ?>
					</label>
				</div>

				<div class="edp-btn-row">
					<button type="submit" class="edp-btn edp-btn-primary"><?php esc_html_e('Save Google Places settings', 'emergencydentalpros'); ?></button>
				</div>
			</form>

			<hr class="edp-divider" />

			<p class="edp-desc"><?php esc_html_e('To verify your key: save settings above, then run a test (one Text Search — does not import cities).', 'emergencydentalpros'); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('edp_seo_google_test', 'edp_seo_google_test_nonce'); ?>
				<input type="hidden" name="action" value="edp_seo_google_test" />
				<button type="submit" id="edp-seo-google-test-btn" class="edp-btn edp-btn-secondary"><?php esc_html_e('Test Google Places API connection', 'emergencydentalpros'); ?></button>
			</form>
		</div>
	</div>


	<script>
	(function () {
		/* ---- Two-way sync ---- */
		var btn     = document.getElementById('edp-sheet-sync-v2-btn');
		var wrap    = document.getElementById('edp-sheet-v2-progress');
		var status  = document.getElementById('edp-sheet-v2-status');
		var result  = document.getElementById('edp-sheet-v2-result');
		var resList = document.getElementById('edp-sheet-v2-result-list');
		var errBox  = document.getElementById('edp-sheet-v2-error');
		var errMsg  = document.getElementById('edp-sheet-v2-error-msg');

		if (btn) {
			var nonce   = <?php echo wp_json_encode(wp_create_nonce('edp_sheet_sync_v2')); ?>;
			var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

			function esc(str) {
				return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			}

			btn.addEventListener('click', function () {
				btn.disabled = true;
				wrap.style.display   = 'block';
				result.style.display = 'none';
				errBox.style.display = 'none';
				resList.innerHTML    = '';
				status.textContent   = <?php echo wp_json_encode(__('Contacting Google Sheets API…', 'emergencydentalpros')); ?>;

				var body = new URLSearchParams({ action: 'edp_sheet_sync_v2', nonce: nonce });

				fetch(ajaxUrl, { method: 'POST', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						btn.disabled       = false;
						status.textContent = '';

						if (!json.success) {
							errMsg.textContent   = (json.data && json.data.message) || <?php echo wp_json_encode(__('Unknown error.', 'emergencydentalpros')); ?>;
							errBox.style.display = 'block';
							return;
						}

						var d    = json.data;
						var rows = [
							<?php echo wp_json_encode(__('Processed (action=TRUE):', 'emergencydentalpros')); ?> + ' <strong>' + esc(d.processed) + '</strong>',
							<?php echo wp_json_encode(__('Skipped (action=FALSE):', 'emergencydentalpros')); ?> + ' <strong>' + esc(d.skipped) + '</strong>',
							<?php echo wp_json_encode(__('Errors (duplicate / DB fail):', 'emergencydentalpros')); ?> + ' <strong>' + esc(d.errors) + '</strong>',
							<?php echo wp_json_encode(__('Written back to sheet:', 'emergencydentalpros')); ?> + ' <strong>' + esc(d.written_back) + '</strong>',
						];
						resList.innerHTML    = rows.map(function (r) { return '<li>' + r + '</li>'; }).join('');
						result.style.display = 'block';
					})
					.catch(function (err) {
						btn.disabled         = false;
						status.textContent   = '';
						errMsg.textContent   = 'Network error: ' + esc(err.message || err);
						errBox.style.display = 'block';
					});
			});
		}

})();
	</script>
</div>
