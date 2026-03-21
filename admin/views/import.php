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

?>
<div class="wrap">
	<h1><?php esc_html_e('Local SEO — Import', 'emergencydentalpros'); ?></h1>

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
</div>
