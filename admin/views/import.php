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

	<?php if (isset($_GET['import_error']) && $_GET['import_error'] === 'custom_path') : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e('The CSV path you entered is not readable. Fix the path or leave the field empty to use the default file in the plugin directory.', 'emergencydentalpros'); ?></p>
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
		<strong><?php esc_html_e('Default file', 'emergencydentalpros'); ?>:</strong>
		<code><?php echo esc_html($default_path); ?></code>
		—
		<?php if (is_readable($default_path)) : ?>
			<span style="color:#008a20;"><?php esc_html_e('readable', 'emergencydentalpros'); ?></span>
		<?php else : ?>
			<span style="color:#b32d2e;"><?php esc_html_e('missing — add raw_data.csv to the plugin directory on this server.', 'emergencydentalpros'); ?></span>
		<?php endif; ?>
	</p>

	<form id="edp-seo-import-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('edp_seo_import', 'edp_seo_import_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_import" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_csv_path"><?php esc_html_e('CSV path (optional)', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_csv_path" type="text" id="edp_csv_path" class="large-text code"
						placeholder="<?php echo esc_attr($default_path); ?>" />
					<p class="description"><?php esc_html_e('Leave empty to use the plugin bundled raw_data.csv (must exist).', 'emergencydentalpros'); ?></p>
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
