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
<div class="wrap">
	<h1><?php esc_html_e('Local SEO — Import', 'emergencydentalpros'); ?></h1>

	<?php if ($google_test_result !== null) : ?>
		<div class="notice notice-<?php echo !empty($google_test_result['ok']) ? 'success' : 'error'; ?> is-dismissible">
			<p><strong><?php esc_html_e('Google Places API test', 'emergencydentalpros'); ?></strong></p>
			<p><?php echo esc_html((string) ($google_test_result['message'] ?? '')); ?></p>
			<?php if (isset($google_test_result['api_calls'])) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of API calls */
						esc_html__('API calls used: %d', 'emergencydentalpros'),
						(int) $google_test_result['api_calls']
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET params are post-redirect-get flags set by our own handlers. ?>
	<?php if ( isset( $_GET['sheet_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Google Sheets URL saved.', 'emergencydentalpros' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['google_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Google Places settings saved.', 'emergencydentalpros' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['google_imported'] ) ) : ?>
		<?php if ( isset( $_GET['google_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Google Places import could not complete. See details below.', 'emergencydentalpros' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Google Places batch finished.', 'emergencydentalpros' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

	<?php if (!empty($persisted['at'])) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: datetime */
				esc_html__('Last saved import log: %s', 'emergencydentalpros'),
				esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $persisted['at']))
			);
			?>
		</p>
	<?php endif; ?>

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* Service account credentials                                        */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>

	<h2><?php esc_html_e('Google Sheets — Service Account', 'emergencydentalpros'); ?></h2>
	<p class="description">
		<?php esc_html_e('Required for the two-way sync. Upload the JSON key file you downloaded from Google Cloud Console after creating the service account. The sheet must be shared with the service account email as Editor.', 'emergencydentalpros'); ?>
	</p>

	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['sa_saved'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Service account credentials saved.', 'emergencydentalpros'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['sa_cleared'])) : ?>
		<div class="notice notice-info is-dismissible"><p><?php esc_html_e('Service account credentials removed.', 'emergencydentalpros'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['sa_error'])) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
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
			</p>
		</div>
	<?php endif; ?>
	<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

	<?php if ($sa_configured) : ?>
		<p>
			<span class="dashicons dashicons-yes" style="color:#0a7040;vertical-align:middle;"></span>
			<strong><?php esc_html_e('Connected:', 'emergencydentalpros'); ?></strong>
			<code><?php echo esc_html($sa_email); ?></code>
		</p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
			<?php wp_nonce_field('edp_sheet_sa_clear', 'edp_sheet_sa_clear_nonce'); ?>
			<input type="hidden" name="action" value="edp_sheet_sa_clear" />
			<?php submit_button(__('Remove credentials', 'emergencydentalpros'), 'delete', 'submit', false); ?>
		</form>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field('edp_sheet_sa_save', 'edp_sheet_sa_nonce'); ?>
			<input type="hidden" name="action" value="edp_sheet_sa_save" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="edp_sa_json"><?php esc_html_e('Service account JSON key', 'emergencydentalpros'); ?></label></th>
					<td>
						<input name="edp_sa_json" type="file" id="edp_sa_json" accept=".json,application/json" required />
						<p class="description">
							<?php esc_html_e('Download from: Google Cloud Console → IAM & Admin → Service Accounts → your account → Keys → Add Key → JSON.', 'emergencydentalpros'); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(__('Upload & save credentials', 'emergencydentalpros'), 'primary', 'submit', false); ?>
		</form>
	<?php endif; ?>

	<hr />

	<?php /* ------------------------------------------------------------------ */ ?>
	<?php /* Two-way sync (v2 — Sheets API)                                     */ ?>
	<?php /* ------------------------------------------------------------------ */ ?>

	<h2><?php esc_html_e('Google Sheets — Two-Way Sync', 'emergencydentalpros'); ?></h2>
	<p class="description">
		<?php esc_html_e('Reads every row where action=TRUE, upserts city data into the database, then writes city_slug / sync_note / last_synced back to the sheet and resets action to FALSE. Requires credentials above.', 'emergencydentalpros'); ?>
	</p>
	<p class="description">
		<?php esc_html_e('Required sheet columns (row 1):', 'emergencydentalpros'); ?><br />
		<code>action, status, type, google_places, faq, city, state_id, state_name, county_name, main_zip, zips, city_slug, sync_note, last_synced</code>
	</p>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:12px;">
		<?php wp_nonce_field('edp_sheet_save_url', 'edp_sheet_nonce'); ?>
		<input type="hidden" name="action" value="edp_sheet_save_url" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_sheet_url"><?php esc_html_e('Google Sheets URL', 'emergencydentalpros'); ?></label></th>
				<td>
					<input
						name="edp_sheet_url"
						type="url"
						id="edp_sheet_url"
						class="large-text code"
						value="<?php echo esc_attr($saved_sheet_url); ?>"
						placeholder="https://docs.google.com/spreadsheets/d/…"
					/>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save URL', 'emergencydentalpros'), 'secondary', 'submit', false); ?>
	</form>

	<?php if ($saved_sheet_url !== '') : ?>
		<p style="margin-bottom:12px;">
			<span class="dashicons dashicons-yes" style="color:#0a7040;vertical-align:middle;"></span>
			<strong><?php esc_html_e('Saved URL:', 'emergencydentalpros'); ?></strong>
			<code><?php echo esc_html($saved_sheet_url); ?></code>
		</p>
	<?php endif; ?>

	<?php
	$v2_ready = $sa_configured && $saved_sheet_url !== '';
	?>

	<div id="edp-sheet-sync-v2-wrap" style="margin-top:12px;">
		<button
			id="edp-sheet-sync-v2-btn"
			class="button button-primary"
			<?php echo $v2_ready ? '' : 'disabled'; ?>
		><?php esc_html_e('Run Two-Way Sync', 'emergencydentalpros'); ?></button>

		<?php if (!$sa_configured) : ?>
			<span class="description" style="margin-left:8px;"><?php esc_html_e('Upload service account credentials first.', 'emergencydentalpros'); ?></span>
		<?php elseif ($saved_sheet_url === '') : ?>
			<span class="description" style="margin-left:8px;"><?php esc_html_e('Save a Sheet URL (below) first.', 'emergencydentalpros'); ?></span>
		<?php endif; ?>
	</div>

	<div id="edp-sheet-v2-progress" style="display:none; margin-top:16px; max-width:600px;">
		<p id="edp-sheet-v2-status" style="margin:4px 0; font-style:italic;"></p>
		<div id="edp-sheet-v2-result" style="display:none; margin-top:8px; padding:10px 14px; background:#f0f6fc; border-left:4px solid #2271b1;">
			<strong><?php esc_html_e('Sync complete', 'emergencydentalpros'); ?></strong>
			<ul id="edp-sheet-v2-result-list" style="margin:6px 0 0; padding-left:1.25em; list-style:disc;"></ul>
		</div>
		<div id="edp-sheet-v2-error" style="display:none; margin-top:8px; padding:10px 14px; background:#fff3cd; border-left:4px solid #f0b429;">
			<strong><?php esc_html_e('Error', 'emergencydentalpros'); ?></strong>
			<p id="edp-sheet-v2-error-msg" style="margin:4px 0 0;"></p>
		</div>
	</div>

	<script>
	(function () {
		var btn     = document.getElementById('edp-sheet-sync-v2-btn');
		var wrap    = document.getElementById('edp-sheet-v2-progress');
		var status  = document.getElementById('edp-sheet-v2-status');
		var result  = document.getElementById('edp-sheet-v2-result');
		var resList = document.getElementById('edp-sheet-v2-result-list');
		var errBox  = document.getElementById('edp-sheet-v2-error');
		var errMsg  = document.getElementById('edp-sheet-v2-error-msg');

		if (!btn) { return; }

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
					btn.disabled   = false;
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
	})();
	</script>

	<hr />

	<h2><?php esc_html_e('Google Places — dentists per city', 'emergencydentalpros'); ?></h2>
	<p class="description">
		<?php esc_html_e('Fetches up to 5 dentist listings per city from the Google Places API and stores them for city landing pages. Requires a Google Cloud project with the Places API enabled. The $200/month free credit covers thousands of requests.', 'emergencydentalpros'); ?>
	</p>
	<p class="description">
		<?php esc_html_e('Setup: Google Cloud Console → APIs & Services → Enable "Places API" → Credentials → Create API Key.', 'emergencydentalpros'); ?>
	</p>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('edp_seo_save_google', 'edp_seo_google_save_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_save_google" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_google_api_key"><?php esc_html_e('Google Places API key', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_google[api_key]" type="password" id="edp_google_api_key" class="large-text code" autocomplete="off"
						placeholder="<?php echo $google_key_set ? esc_attr__('Leave blank to keep the saved key', 'emergencydentalpros') : esc_attr__('Paste API key from Google Cloud Console', 'emergencydentalpros'); ?>" />
					<?php if ($google_key_set) : ?>
						<p class="description"><?php esc_html_e('A key is already saved. Enter a new value only to replace it.', 'emergencydentalpros'); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edp_google_term"><?php esc_html_e('Search term', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_google[term]" type="text" id="edp_google_term" class="regular-text"
						value="<?php echo esc_attr((string) ($google['term'] ?? 'emergency dentist')); ?>" />
					<p class="description"><?php esc_html_e('Combined with city + state, e.g. "emergency dentist Birmingham AL".', 'emergencydentalpros'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edp_google_limit"><?php esc_html_e('Max businesses per city', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_google[limit]" type="number" id="edp_google_limit" min="1" max="5" step="1"
						value="<?php echo esc_attr((string) (int) ($google['limit'] ?? 5)); ?>" />
					<p class="description"><?php esc_html_e('Maximum 5.', 'emergencydentalpros'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Opening hours', 'emergencydentalpros'); ?></th>
				<td>
					<label>
						<input name="edp_google[fetch_details]" type="checkbox" value="1" <?php checked(!empty($google['fetch_details'])); ?> />
						<?php esc_html_e('Fetch full hours + phone (one extra API call per business)', 'emergencydentalpros'); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Save Google Places settings', 'emergencydentalpros')); ?>
	</form>

	<p class="description">
		<?php esc_html_e('To verify your key: save settings above, then run a test (one Text Search — does not import cities).', 'emergencydentalpros'); ?>
	</p>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('edp_seo_google_test', 'edp_seo_google_test_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_google_test" />
		<?php
		submit_button(
			__('Test Google Places API connection', 'emergencydentalpros'),
			'secondary',
			'submit',
			false,
			['id' => 'edp-seo-google-test-btn']
		);
		?>
	</form>

	<?php
	$google_last = get_transient('edp_seo_last_google_import');
	if (is_array($google_last)) {
		delete_transient('edp_seo_last_google_import');
	}
	?>
	<?php if (is_array($google_last)) : ?>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					/* translators: 1: cities processed, 2: API calls */
					esc_html__('Last Google batch — Cities processed: %1$d — API calls (approx.): %2$d', 'emergencydentalpros'),
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
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="google_offset"><?php esc_html_e('City offset', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="google_offset" type="number" id="google_offset" min="0" step="1" value="0" />
					<p class="description"><?php esc_html_e('Row offset in the locations table (order by id). Increase after each batch to continue where you left off.', 'emergencydentalpros'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="google_limit"><?php esc_html_e('Cities in this batch', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="google_limit" type="number" id="google_limit" min="1" max="300" step="1" value="25" />
					<p class="description"><?php esc_html_e('Max 300 per batch. Each city = 1 Text Search call + up to 5 Details calls if hours are enabled.', 'emergencydentalpros'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Hours in this batch', 'emergencydentalpros'); ?></th>
				<td>
					<label>
						<input name="google_fetch_details" type="checkbox" value="1" <?php checked(EDP_Google_Places_Config::should_fetch_details()); ?> />
						<?php esc_html_e('Fetch opening hours + phone (more API calls per city)', 'emergencydentalpros'); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(__('Run Google Places batch import', 'emergencydentalpros'), 'secondary', 'edp-google-import-submit'); ?>
	</form>

	<div id="edp-google-progress" style="display:none; margin-top:16px; max-width:600px;">
		<div style="background:#ddd; border-radius:3px; height:18px; overflow:hidden; margin-bottom:8px;">
			<div id="edp-google-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.25s;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
		</div>
		<p id="edp-google-status" style="margin:4px 0; font-style:italic;"></p>
		<div id="edp-google-error-wrap" style="display:none; margin-top:8px; background:#fff3cd; border-left:4px solid #f0b429; padding:8px 12px;">
			<strong><?php esc_html_e('Warnings', 'emergencydentalpros'); ?></strong>
			<ul id="edp-google-errors" style="margin:4px 0 0; padding-left:1.25em;"></ul>
		</div>
	</div>

	<script>
	(function () {
		var form    = document.getElementById('edp-google-import-form');
		var btn     = document.getElementById('edp-google-import-submit');
		var wrap    = document.getElementById('edp-google-progress');
		var bar     = document.getElementById('edp-google-bar');
		var status  = document.getElementById('edp-google-status');
		var errWrap = document.getElementById('edp-google-error-wrap');
		var errList = document.getElementById('edp-google-errors');

		if (!form || !btn) { return; }

		var nonce   = <?php echo wp_json_encode(wp_create_nonce('edp_google_import_step')); ?>;
		var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

		function esc(str) {
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

			btn.disabled = true;
			wrap.style.display = 'block';
			errWrap.style.display = 'none';
			errList.innerHTML = '';
			setBar(0);

			var step         = 0;
			var totalApiCalls = 0;
			var allMessages  = [];

			function processNext() {
				if (step >= total) {
					finish();
					return;
				}

				status.textContent = <?php echo wp_json_encode(__('City', 'emergencydentalpros')); ?> + ' ' + (step + 1) + ' / ' + total + '…';
				setBar(Math.round((step / total) * 100));

				var body = new URLSearchParams({
					action:        'edp_google_import_step',
					nonce:         nonce,
					offset:        offset,
					step:          step,
					total:         total,
					fetch_details: details,
				});

				fetch(ajaxUrl, { method: 'POST', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (!json.success) {
							status.textContent = 'Error: ' + esc((json.data && json.data.message) ? json.data.message : 'Unknown');
							btn.disabled = false;
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
						status.textContent = 'Network error: ' + esc(err.message || err);
						btn.disabled = false;
					});
			}

			function finish() {
				setBar(100);
				status.textContent = <?php echo wp_json_encode(__('Done', 'emergencydentalpros')); ?> + ' — ' + step + ' <?php esc_html_e('cities processed', 'emergencydentalpros'); ?>, ~' + totalApiCalls + ' <?php esc_html_e('API calls', 'emergencydentalpros'); ?>';
				btn.disabled = false;
				if (allMessages.length) {
					errList.innerHTML = allMessages.map(function (m) { return '<li><code>' + esc(m) + '</code></li>'; }).join('');
					errWrap.style.display = 'block';
				}
			}

			processNext();
		});
	})();
	</script>
</div>
