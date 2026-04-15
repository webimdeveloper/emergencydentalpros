<?php
/**
 * CSV import screen.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

$last = get_transient('edp_seo_last_import');
$default_path = EDP_PLUGIN_DIR . 'raw_data.csv';
$persisted = get_option(EDP_Admin::OPTION_IMPORT_LOG, []);

if (!is_array($persisted)) {
	$persisted = [];
}

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

	<?php if (isset($_GET['google_saved'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Google Places settings saved.', 'emergencydentalpros'); ?></p></div>
	<?php endif; ?>

	<?php if (isset($_GET['google_imported'])) : ?>
		<?php if (isset($_GET['google_error'])) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e('Google Places import could not complete. See details below.', 'emergencydentalpros'); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Google Places batch finished.', 'emergencydentalpros'); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (isset($_GET['imported'])) : ?>
		<?php if (isset($_GET['import_error'])) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e('Import finished with errors. See details below.', 'emergencydentalpros'); ?></p>
			</div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Import finished.', 'emergencydentalpros'); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	$upload_reason = isset($_GET['reason']) ? sanitize_key((string) wp_unslash($_GET['reason'])) : '';
	?>
	<?php if (isset($_GET['import_error']) && $_GET['import_error'] === 'upload') : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				switch ($upload_reason) {
					case 'ini':
					case 'form':
						esc_html_e('The CSV file is too large for the server’s current PHP upload limits (upload_max_filesize / post_max_size). Increase those values, or run the import via WP-CLI on the server instead.', 'emergencydentalpros');
						break;
					case 'partial':
						esc_html_e('The file upload was interrupted. Try again with a stable connection.', 'emergencydentalpros');
						break;
					case 'tmp':
					case 'write':
						esc_html_e('The server could not store the uploaded file (temporary directory or permissions). Check PHP upload_tmp_dir and filesystem permissions.', 'emergencydentalpros');
						break;
					case 'ext':
						esc_html_e('A PHP extension stopped this upload. Ask your host to review PHP configuration.', 'emergencydentalpros');
						break;
					default:
						esc_html_e('The file could not be uploaded. Try again or use WP-CLI: wp edp-seo import /path/to/file.csv', 'emergencydentalpros');
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if (isset($_GET['import_error']) && $_GET['import_error'] === 'upload_wp') : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e('WordPress rejected the uploaded file (type or security checks). Use a .csv file, or import via WP-CLI with a path on the server.', 'emergencydentalpros'); ?></p>
		</div>
	<?php endif; ?>

	<?php if (is_array($last)) : ?>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					/* translators: 1: rows read, 2: skipped, 3: city groups */
					esc_html__('Last run — Rows read: %1$d — Skipped: %2$d — City groups upserted: %3$d', 'emergencydentalpros'),
					(int) ($last['rows'] ?? 0),
					(int) ($last['skipped'] ?? 0),
					(int) ($last['groups'] ?? 0)
				);
				?>
			</p>
			<?php if (!empty($last['error'])) : ?>
				<p><strong><?php esc_html_e('Error', 'emergencydentalpros'); ?>:</strong> <code><?php echo esc_html((string) $last['error']); ?></code></p>
			<?php endif; ?>
			<?php if (!empty($last['path'])) : ?>
				<p><code><?php echo esc_html((string) $last['path']); ?></code></p>
			<?php endif; ?>
		</div>
		<?php delete_transient('edp_seo_last_import'); ?>
	<?php endif; ?>

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

	<p><?php esc_html_e('Imports USA 50 states + DC only. Rows are grouped by state and city; ZIP codes are merged.', 'emergencydentalpros'); ?></p>
	<p class="description">
		<?php esc_html_e('Tip: After a successful import, open your site at /locations/ to browse virtual pages. If the request times out, use WP-CLI: wp edp-seo import', 'emergencydentalpros'); ?>
	</p>

	<p>
		<strong><?php esc_html_e('Server default (optional fallback)', 'emergencydentalpros'); ?>:</strong>
		<code><?php echo esc_html($default_path); ?></code>
		—
		<?php if (is_readable($default_path)) : ?>
			<span style="color:#008a20;"><?php esc_html_e('readable — you can run import without choosing a file', 'emergencydentalpros'); ?></span>
		<?php else : ?>
			<span style="color:#b32d2e;"><?php esc_html_e('not found — choose a CSV file below to upload.', 'emergencydentalpros'); ?></span>
		<?php endif; ?>
	</p>

	<p class="description">
		<?php
		printf(
			/* translators: %s: max upload size, e.g. "8 MB" */
			esc_html__('Maximum upload size (PHP): %s', 'emergencydentalpros'),
			esc_html(size_format(wp_max_upload_size()))
		);
		?>
	</p>

	<form id="edp-seo-import-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field('edp_seo_import', 'edp_seo_import_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_import" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_csv_file"><?php esc_html_e('CSV file', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_csv_file" type="file" id="edp_csv_file" accept=".csv,text/csv" />
					<p class="description">
						<?php esc_html_e('Choose your raw_data.csv (or any compatible export) from your computer. If you leave this empty, the importer uses the server file above when it exists.', 'emergencydentalpros'); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php
		submit_button(
			__('Run import', 'emergencydentalpros'),
			'primary',
			'submit',
			true,
			[
				'id' => 'edp-seo-import-submit',
			]
		);
		?>
	</form>
	<script>
	(function () {
		var form = document.getElementById('edp-seo-import-form');
		var btn = document.getElementById('edp-seo-import-submit');
		if (!form || !btn) {
			return;
		}
		form.addEventListener('submit', function () {
			btn.disabled = true;
			btn.classList.add('disabled');
			btn.value = <?php echo wp_json_encode(__('Importing…', 'emergencydentalpros')); ?>;
			var msg = document.createElement('p');
			msg.className = 'description';
			msg.style.marginTop = '12px';
			msg.textContent = <?php echo wp_json_encode(__('Working… Large CSV files can take a minute. Do not refresh or double-click.', 'emergencydentalpros')); ?>;
			form.appendChild(msg);
		});
	})();
	</script>

	<hr />

	<h2><?php esc_html_e(‘Google Places — dentists per city’, ‘emergencydentalpros’); ?></h2>
	<p class="description">
		<?php esc_html_e(‘Fetches up to 5 dentist listings per city from the Google Places API and stores them for city landing pages. Requires a Google Cloud project with the Places API enabled. The $200/month free credit covers thousands of requests.’, ‘emergencydentalpros’); ?>
	</p>
	<p class="description">
		<?php esc_html_e(‘Setup: Google Cloud Console → APIs & Services → Enable "Places API" → Credentials → Create API Key.’, ‘emergencydentalpros’); ?>
	</p>

	<form method="post" action="<?php echo esc_url(admin_url(‘admin-post.php’)); ?>">
		<?php wp_nonce_field(‘edp_seo_save_google’, ‘edp_seo_google_save_nonce’); ?>
		<input type="hidden" name="action" value="edp_seo_save_google" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_google_api_key"><?php esc_html_e(‘Google Places API key’, ‘emergencydentalpros’); ?></label></th>
				<td>
					<input name="edp_google[api_key]" type="password" id="edp_google_api_key" class="large-text code" autocomplete="off"
						placeholder="<?php echo $google_key_set ? esc_attr__(‘Leave blank to keep the saved key’, ‘emergencydentalpros’) : esc_attr__(‘Paste API key from Google Cloud Console’, ‘emergencydentalpros’); ?>" />
					<?php if ($google_key_set) : ?>
						<p class="description"><?php esc_html_e(‘A key is already saved. Enter a new value only to replace it.’, ‘emergencydentalpros’); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edp_google_term"><?php esc_html_e(‘Search term’, ‘emergencydentalpros’); ?></label></th>
				<td>
					<input name="edp_google[term]" type="text" id="edp_google_term" class="regular-text"
						value="<?php echo esc_attr((string) ($google[‘term’] ?? ‘emergency dentist’)); ?>" />
					<p class="description"><?php esc_html_e(‘Combined with city + state, e.g. "emergency dentist Birmingham AL".’, ‘emergencydentalpros’); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="edp_google_limit"><?php esc_html_e(‘Max businesses per city’, ‘emergencydentalpros’); ?></label></th>
				<td>
					<input name="edp_google[limit]" type="number" id="edp_google_limit" min="1" max="5" step="1"
						value="<?php echo esc_attr((string) (int) ($google[‘limit’] ?? 5)); ?>" />
					<p class="description"><?php esc_html_e(‘Maximum 5.’, ‘emergencydentalpros’); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e(‘Opening hours’, ‘emergencydentalpros’); ?></th>
				<td>
					<label>
						<input name="edp_google[fetch_details]" type="checkbox" value="1" <?php checked(!empty($google[‘fetch_details’])); ?> />
						<?php esc_html_e(‘Fetch full hours + phone (one extra API call per business)’, ‘emergencydentalpros’); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(__(‘Save Google Places settings’, ‘emergencydentalpros’)); ?>
	</form>

	<p class="description">
		<?php esc_html_e(‘To verify your key: save settings above, then run a test (one Text Search — does not import cities).’, ‘emergencydentalpros’); ?>
	</p>
	<form method="post" action="<?php echo esc_url(admin_url(‘admin-post.php’)); ?>">
		<?php wp_nonce_field(‘edp_seo_google_test’, ‘edp_seo_google_test_nonce’); ?>
		<input type="hidden" name="action" value="edp_seo_google_test" />
		<?php
		submit_button(
			__(‘Test Google Places API connection’, ‘emergencydentalpros’),
			‘secondary’,
			‘submit’,
			false,
			[‘id’ => ‘edp-seo-google-test-btn’]
		);
		?>
	</form>

	<?php
	$google_last = get_transient(‘edp_seo_last_google_import’);
	if (is_array($google_last)) {
		delete_transient(‘edp_seo_last_google_import’);
	}
	?>
	<?php if (is_array($google_last)) : ?>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					/* translators: 1: cities processed, 2: API calls */
					esc_html__(‘Last Google batch — Cities processed: %1$d — API calls (approx.): %2$d’, ‘emergencydentalpros’),
					(int) ($google_last[‘processed’] ?? 0),
					(int) ($google_last[‘api_calls’] ?? 0)
				);
				?>
			</p>
			<?php if (!empty($google_last[‘error’])) : ?>
				<p><code><?php echo esc_html((string) $google_last[‘error’]); ?></code></p>
			<?php endif; ?>
			<?php if (!empty($google_last[‘messages’]) && is_array($google_last[‘messages’])) : ?>
				<ul>
					<?php foreach (array_slice($google_last[‘messages’], 0, 20) as $msg) : ?>
						<li><code><?php echo esc_html((string) $msg); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url(admin_url(‘admin-post.php’)); ?>">
		<?php wp_nonce_field(‘edp_seo_google_import’, ‘edp_seo_google_import_nonce’); ?>
		<input type="hidden" name="action" value="edp_seo_google_import" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="google_offset"><?php esc_html_e(‘City offset’, ‘emergencydentalpros’); ?></label></th>
				<td>
					<input name="google_offset" type="number" id="google_offset" min="0" step="1" value="0" />
					<p class="description"><?php esc_html_e(‘Row offset in the locations table (order by id). Increase after each batch to continue where you left off.’, ‘emergencydentalpros’); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="google_limit"><?php esc_html_e(‘Cities in this batch’, ‘emergencydentalpros’); ?></label></th>
				<td>
					<input name="google_limit" type="number" id="google_limit" min="1" max="300" step="1" value="25" />
					<p class="description"><?php esc_html_e(‘Max 300 per batch. Each city = 1 Text Search call + up to 5 Details calls if hours are enabled.’, ‘emergencydentalpros’); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e(‘Hours in this batch’, ‘emergencydentalpros’); ?></th>
				<td>
					<label>
						<input name="google_fetch_details" type="checkbox" value="1" <?php checked(EDP_Google_Places_Config::should_fetch_details()); ?> />
						<?php esc_html_e(‘Fetch opening hours + phone (more API calls per city)’, ‘emergencydentalpros’); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(__(‘Run Google Places batch import’, ‘emergencydentalpros’), ‘secondary’); ?>
	</form>
</div>
