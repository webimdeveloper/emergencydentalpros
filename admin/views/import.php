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
<style>
@import url('https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap');

#edp-import-wrap {
	font-family: 'Lato', sans-serif;
	color: #3A3541;
	max-width: 900px;
}
#edp-import-wrap h1 {
	font-family: 'Lato', sans-serif;
	font-weight: 700;
	font-size: 25.63px;
	color: #3A3541;
	margin-bottom: 4px;
}
#edp-import-wrap .edp-subtitle {
	font-size: 14.22px;
	color: #89868D;
	margin-top: 0;
	margin-bottom: 24px;
}

/* Cards */
.edp-card {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
	margin-bottom: 24px;
	overflow: hidden;
}
.edp-card-header {
	background: #E7E7F4;
	padding: 16px 20px;
	font-weight: 500;
	font-size: 16px;
	color: #3A3541;
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.edp-card-header .edp-card-header-badge {
	font-size: 12px;
	font-weight: 400;
	color: #89868D;
	background: #fff;
	border: 1px solid #DBDCDE;
	border-radius: 4px;
	padding: 2px 10px;
}
.edp-card-header .edp-badge-ok {
	color: #0a7040;
	border-color: #b7e4c7;
	background: #f0faf4;
}
.edp-card-body {
	padding: 20px;
}
.edp-card-body p.edp-desc {
	font-size: 14.22px;
	color: #89868D;
	margin-top: 0;
	margin-bottom: 16px;
}
.edp-card-body code.edp-code-block {
	display: block;
	background: #F4F5F9;
	border: 1px solid #DBDCDE;
	border-radius: 6px;
	padding: 10px 14px;
	font-size: 12px;
	color: #3A3541;
	word-break: break-all;
	margin-bottom: 16px;
}

/* Form rows */
.edp-form-row {
	margin-bottom: 16px;
}
.edp-form-row label {
	display: block;
	font-size: 14.22px;
	font-weight: 500;
	color: #3A3541;
	margin-bottom: 6px;
}
.edp-form-row input[type="text"],
.edp-form-row input[type="url"],
.edp-form-row input[type="password"],
.edp-form-row input[type="number"],
.edp-form-row input[type="file"] {
	background: #F4F5F9;
	border: 1px solid #DBDCDE;
	border-radius: 8px;
	padding: 10px 14px;
	font-size: 14.22px;
	font-family: 'Lato', sans-serif;
	color: #3A3541;
	width: 100%;
	max-width: 500px;
	box-sizing: border-box;
}
.edp-form-row input[type="file"] {
	padding: 8px 14px;
}
.edp-form-row input:focus {
	outline: none;
	border-color: #6E39CB;
	box-shadow: 0 0 0 2px rgba(110,57,203,0.15);
}
.edp-form-row input[type="number"] {
	max-width: 120px;
}
.edp-form-row .edp-hint {
	font-size: 12.64px;
	color: #89868D;
	margin-top: 4px;
}
.edp-form-row label.edp-checkbox-label {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 400;
	cursor: pointer;
}
.edp-form-row label.edp-checkbox-label input[type="checkbox"] {
	width: 16px;
	height: 16px;
	accent-color: #6E39CB;
	flex-shrink: 0;
}

/* Form row two-col */
.edp-form-row-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

/* Buttons */
.edp-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: 4px;
	height: 32px;
	padding: 0 20px;
	font-size: 12.64px;
	font-family: 'Lato', sans-serif;
	cursor: pointer;
	text-decoration: none;
	transition: opacity 0.15s;
}
.edp-btn:hover { opacity: 0.88; }
.edp-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.edp-btn-primary {
	background: #6E39CB;
	color: #fff;
}
.edp-btn-secondary {
	background: #fff;
	color: #3A3541;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
}
.edp-btn-danger {
	background: #fff;
	color: #c0392b;
	box-shadow: 0 0 4px rgba(0,0,0,0.15);
}
.edp-btn-row {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-top: 20px;
}

/* Connected status row */
.edp-connected-row {
	display: flex;
	align-items: center;
	gap: 8px;
	background: #f0faf4;
	border: 1px solid #b7e4c7;
	border-radius: 8px;
	padding: 10px 14px;
	margin-bottom: 16px;
	font-size: 14.22px;
}
.edp-connected-row .dashicons {
	color: #0a7040;
	font-size: 18px;
	line-height: 1;
}
.edp-connected-row code {
	background: transparent;
	color: #0a7040;
	font-size: 13px;
}

/* Notices */
.edp-notice {
	border-radius: 8px;
	padding: 12px 16px;
	margin-bottom: 16px;
	font-size: 14.22px;
	display: flex;
	align-items: flex-start;
	gap: 8px;
}
.edp-notice-success { background: #f0faf4; border-left: 4px solid #2ecc71; color: #0a7040; }
.edp-notice-error   { background: #fff5f5; border-left: 4px solid #e74c3c; color: #c0392b; }
.edp-notice-info    { background: #f0f6fc; border-left: 4px solid #2271b1; color: #1a4a7a; }
.edp-notice-warning { background: #fff3cd; border-left: 4px solid #f0b429; color: #7a5200; }

/* Progress UI */
.edp-progress-wrap {
	display: none;
	margin-top: 16px;
}
.edp-progress-bar-track {
	background: #DBDCDE;
	border-radius: 4px;
	height: 8px;
	overflow: hidden;
	margin-bottom: 8px;
}
.edp-progress-bar-fill {
	background: #6E39CB;
	height: 100%;
	width: 0%;
	transition: width 0.25s;
	border-radius: 4px;
}
.edp-progress-status {
	font-size: 13px;
	color: #89868D;
	font-style: italic;
	margin: 4px 0;
}
.edp-result-box {
	display: none;
	margin-top: 8px;
	padding: 10px 14px;
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
	border-radius: 0 6px 6px 0;
}
.edp-result-box ul { margin: 6px 0 0; padding-left: 1.25em; list-style: disc; }
.edp-error-box {
	display: none;
	margin-top: 8px;
	padding: 10px 14px;
	background: #fff3cd;
	border-left: 4px solid #f0b429;
	border-radius: 0 6px 6px 0;
}
.edp-url-display {
	display: flex;
	align-items: center;
	gap: 8px;
	background: #F4F5F9;
	border: 1px solid #DBDCDE;
	border-radius: 8px;
	padding: 10px 14px;
	margin-bottom: 16px;
	font-size: 13px;
	word-break: break-all;
}
.edp-url-display .dashicons { color: #0a7040; flex-shrink: 0; }

/* Last import info box */
.edp-import-info {
	background: #F4F5F9;
	border: 1px solid #DBDCDE;
	border-radius: 8px;
	padding: 14px;
	margin-bottom: 16px;
	font-size: 13px;
	color: #3A3541;
}
.edp-import-info ul { margin: 6px 0 0; padding-left: 1.25em; }
.edp-divider {
	border: none;
	border-top: 1px solid #DBDCDE;
	margin: 4px 0 16px;
}
</style>

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
	<?php if (isset($_GET['google_imported'])) : ?>
		<?php if (isset($_GET['google_error'])) : ?>
			<div class="edp-notice edp-notice-error"><?php esc_html_e('Google Places import could not complete. See details below.', 'emergencydentalpros'); ?></div>
		<?php else : ?>
			<div class="edp-notice edp-notice-success"><?php esc_html_e('Google Places batch finished.', 'emergencydentalpros'); ?></div>
		<?php endif; ?>
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

	<?php if (!empty($persisted['at'])) : ?>
		<div class="edp-notice edp-notice-info">
			<?php
			printf(
				/* translators: 1: datetime */
				esc_html__('Last saved import log: %s', 'emergencydentalpros'),
				esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $persisted['at']))
			);
			?>
		</div>
	<?php endif; ?>

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* CARD 1 — Service Account                                           */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>
	<div class="edp-card">
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

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* CARD 4 — Google Places Batch Import                                */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>
	<div class="edp-card">
		<div class="edp-card-header">
			<?php esc_html_e('Google Places — Batch Import', 'emergencydentalpros'); ?>
		</div>
		<div class="edp-card-body">

			<?php
			$google_last = get_transient('edp_seo_last_google_import');
			if (is_array($google_last)) {
				delete_transient('edp_seo_last_google_import');
			}
			?>
			<?php if (is_array($google_last)) : ?>
				<div class="edp-import-info">
					<strong><?php esc_html_e('Last batch result', 'emergencydentalpros'); ?></strong>
					<p style="margin:4px 0;">
						<?php
						printf(
							/* translators: 1: cities processed, 2: API calls */
							esc_html__('Cities processed: %1$d — API calls (approx.): %2$d', 'emergencydentalpros'),
							(int) ($google_last['processed'] ?? 0),
							(int) ($google_last['api_calls'] ?? 0)
						);
						?>
					</p>
					<?php if (!empty($google_last['error'])) : ?>
						<p><code><?php echo esc_html((string) $google_last['error']); ?></code></p>
					<?php endif; ?>
					<?php if (!empty($google_last['messages']) && is_array($google_last['messages'])) : ?>
						<ul>
							<?php foreach (array_slice($google_last['messages'], 0, 20) as $msg) : ?>
								<li><code><?php echo esc_html((string) $msg); ?></code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form id="edp-google-import-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('edp_seo_google_import', 'edp_seo_google_import_nonce'); ?>
				<input type="hidden" name="action" value="edp_seo_google_import" />

				<div class="edp-form-row-grid">
					<div class="edp-form-row" style="margin-bottom:0;">
						<label for="google_offset"><?php esc_html_e('City offset', 'emergencydentalpros'); ?></label>
						<input name="google_offset" type="number" id="google_offset" min="0" step="1" value="0" />
						<p class="edp-hint"><?php esc_html_e('Row offset in the locations table (order by id). Increase after each batch to continue where you left off.', 'emergencydentalpros'); ?></p>
					</div>
					<div class="edp-form-row" style="margin-bottom:0;">
						<label for="google_limit"><?php esc_html_e('Cities in this batch', 'emergencydentalpros'); ?></label>
						<input name="google_limit" type="number" id="google_limit" min="1" max="300" step="1" value="25" />
						<p class="edp-hint"><?php esc_html_e('Max 300 per batch. Each city = 1 Text Search call + up to 5 Details calls if hours are enabled.', 'emergencydentalpros'); ?></p>
					</div>
				</div>

				<div class="edp-form-row" style="margin-top:16px;">
					<label class="edp-checkbox-label">
						<input name="google_fetch_details" type="checkbox" value="1" <?php checked(EDP_Google_Places_Config::should_fetch_details()); ?> />
						<?php esc_html_e('Fetch opening hours + phone (more API calls per city)', 'emergencydentalpros'); ?>
					</label>
				</div>

				<div class="edp-btn-row">
					<button type="submit" id="edp-google-import-submit" class="edp-btn edp-btn-primary"><?php esc_html_e('Run Google Places batch import', 'emergencydentalpros'); ?></button>
				</div>
			</form>

			<div id="edp-google-progress" class="edp-progress-wrap">
				<div class="edp-progress-bar-track">
					<div id="edp-google-bar" class="edp-progress-bar-fill" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
				<p id="edp-google-status" class="edp-progress-status"></p>
				<div id="edp-google-error-wrap" class="edp-error-box" style="margin-top:8px;">
					<strong><?php esc_html_e('Warnings', 'emergencydentalpros'); ?></strong>
					<ul id="edp-google-errors" style="margin:4px 0 0; padding-left:1.25em;"></ul>
				</div>
			</div>
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

		/* ---- Batch import ---- */
		var form    = document.getElementById('edp-google-import-form');
		var impBtn  = document.getElementById('edp-google-import-submit');
		var impWrap = document.getElementById('edp-google-progress');
		var bar     = document.getElementById('edp-google-bar');
		var impSt   = document.getElementById('edp-google-status');
		var errWr   = document.getElementById('edp-google-error-wrap');
		var errLst  = document.getElementById('edp-google-errors');

		if (form && impBtn) {
			var impNonce   = <?php echo wp_json_encode(wp_create_nonce('edp_google_import_step')); ?>;
			var impAjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

			function escH(str) {
				return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			}

			function setBar(pct) {
				bar.style.width = pct + '%';
				bar.setAttribute('aria-valuenow', pct);
			}

			form.addEventListener('submit', function (e) {
				e.preventDefault();

				var fd      = new FormData(form);
				var offset  = parseInt(fd.get('google_offset'), 10) || 0;
				var total   = Math.min(300, Math.max(1, parseInt(fd.get('google_limit'), 10) || 25));
				var details = fd.has('google_fetch_details') ? '1' : '';

				impBtn.disabled        = true;
				impWrap.style.display  = 'block';
				errWr.style.display    = 'none';
				errLst.innerHTML       = '';
				setBar(0);

				var step          = 0;
				var totalApiCalls = 0;
				var allMessages   = [];

				function processNext() {
					if (step >= total) {
						finish();
						return;
					}

					impSt.textContent = <?php echo wp_json_encode(__('City', 'emergencydentalpros')); ?> + ' ' + (step + 1) + ' / ' + total + '…';
					setBar(Math.round((step / total) * 100));

					var body = new URLSearchParams({
						action:        'edp_google_import_step',
						nonce:         impNonce,
						offset:        offset,
						step:          step,
						total:         total,
						fetch_details: details,
					});

					fetch(impAjaxUrl, { method: 'POST', body: body })
						.then(function (r) { return r.json(); })
						.then(function (json) {
							if (!json.success) {
								impSt.textContent = 'Error: ' + escH((json.data && json.data.message) ? json.data.message : 'Unknown');
								impBtn.disabled = false;
								return;
							}
							totalApiCalls += (json.data.api_calls || 0);
							if (json.data.messages && json.data.messages.length) {
								allMessages = allMessages.concat(json.data.messages);
							}
							step = json.data.step;
							if (json.data.done) {
								finish();
							} else {
								processNext();
							}
						})
						.catch(function (err) {
							impSt.textContent = 'Network error: ' + escH(err.message || err);
							impBtn.disabled = false;
						});
				}

				function finish() {
					setBar(100);
					impSt.textContent = <?php echo wp_json_encode(__('Done', 'emergencydentalpros')); ?> + ' — ' + step + ' <?php esc_html_e('cities processed', 'emergencydentalpros'); ?>, ~' + totalApiCalls + ' <?php esc_html_e('API calls', 'emergencydentalpros'); ?>';
					impBtn.disabled = false;
					if (allMessages.length) {
						errLst.innerHTML    = allMessages.map(function (m) { return '<li><code>' + escH(m) + '</code></li>'; }).join('');
						errWr.style.display = 'block';
					}
				}

				processNext();
			});
		}
	})();
	</script>
</div>
