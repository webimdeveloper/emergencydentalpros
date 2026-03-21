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
 */

if (!defined('ABSPATH')) {
    exit;
}

$edp_seo_debug = $edp_seo_debug ?? false;
$edp_debug_data = $edp_debug_data ?? [];

?>
<div class="wrap">
	<h1><?php esc_html_e('Local SEO — Locations', 'emergencydentalpros'); ?></h1>
	<p><?php esc_html_e('Map a location to an existing post/page ID, create a CPT override from global templates, or clear overrides.', 'emergencydentalpros'); ?></p>
	<p class="description">
		<?php esc_html_e('Front-end URLs (not listed in Pages): /locations/ — state list; /locations/{state}/ — cities; /locations/{state}/{city}/ — city landing. Flush permalinks if you get 404s.', 'emergencydentalpros'); ?>
	</p>

	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e('Database', 'emergencydentalpros'); ?>:</strong>
			<?php
			printf(
				/* translators: %d: row count */
				esc_html(_n('%d location row stored.', '%d location rows stored.', $location_count, 'emergencydentalpros')),
				(int) $location_count
			);
			?>
		</p>
		<p>
			<strong><?php esc_html_e('Default CSV', 'emergencydentalpros'); ?>:</strong>
			<code><?php echo esc_html($default_csv); ?></code>
			—
			<?php if ($default_csv_ok) : ?>
				<span style="color:#008a20;"><?php esc_html_e('readable', 'emergencydentalpros'); ?></span>
			<?php else : ?>
				<span style="color:#b32d2e;"><?php esc_html_e('not found or not readable — upload raw_data.csv into the plugin folder or set a full path on Import.', 'emergencydentalpros'); ?></span>
			<?php endif; ?>
		</p>
		<?php if (!empty($import_log['at'])) : ?>
			<p>
				<strong><?php esc_html_e('Last import', 'emergencydentalpros'); ?>:</strong>
				<?php
				echo esc_html(
					wp_date(
						get_option('date_format') . ' ' . get_option('time_format'),
						(int) $import_log['at']
					)
				);
				?>
				—
				<?php
				printf(
					/* translators: 1: rows, 2: skipped, 3: groups */
					esc_html__('rows %1$d, skipped %2$d, city groups %3$d', 'emergencydentalpros'),
					(int) ($import_log['rows'] ?? 0),
					(int) ($import_log['skipped'] ?? 0),
					(int) ($import_log['groups'] ?? 0)
				);
				?>
				<?php if (!empty($import_log['path'])) : ?>
					<br /><code><?php echo esc_html((string) $import_log['path']); ?></code>
				<?php endif; ?>
				<?php if (!empty($import_log['error']) || (isset($import_log['ok']) && ! $import_log['ok'])) : ?>
					<br /><span style="color:#b32d2e;">
						<?php
						$code = isset($import_log['error']) ? (string) $import_log['error'] : '';
						$messages = [
							'file_not_readable' => __('CSV could not be read (wrong path or permissions).', 'emergencydentalpros'),
							'fopen_failed' => __('Could not open CSV file.', 'emergencydentalpros'),
							'empty_or_invalid_csv' => __('CSV was empty or invalid.', 'emergencydentalpros'),
							'missing_columns' => __('CSV is missing required columns (zip, city, state_id, state_name).', 'emergencydentalpros'),
							'custom_path_not_readable' => __('The custom CSV path was not readable.', 'emergencydentalpros'),
						];
						echo esc_html($messages[$code] ?? __('Import reported an error. Check the path and try again.', 'emergencydentalpros'));
						?>
					</span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php
		$rows_read = (int) ($import_log['rows'] ?? 0);
		$groups = (int) ($import_log['groups'] ?? 0);
		if ($location_count === 0 && $rows_read > 0 && $groups === 0 && empty($import_log['error'])) :
			?>
			<p><strong><?php esc_html_e('Note:', 'emergencydentalpros'); ?></strong>
			<?php esc_html_e('The last import read rows from the file but produced zero city groups. Rows outside USA 50 states + DC are skipped — confirm your CSV contains US rows.', 'emergencydentalpros'); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=edp-seo-import')); ?>"><?php esc_html_e('Go to Import', 'emergencydentalpros'); ?></a>
			<?php if (! $edp_seo_debug) : ?>
				<a class="button" style="margin-left:8px;" href="<?php echo esc_url(add_query_arg('edp_seo_debug', '1', admin_url('admin.php?page=edp-seo-locations'))); ?>"><?php esc_html_e('Show diagnostics', 'emergencydentalpros'); ?></a>
			<?php else : ?>
				<a class="button" style="margin-left:8px;" href="<?php echo esc_url(remove_query_arg('edp_seo_debug', admin_url('admin.php?page=edp-seo-locations'))); ?>"><?php esc_html_e('Hide diagnostics', 'emergencydentalpros'); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<?php if ($edp_seo_debug && ! empty($edp_debug_data)) : ?>
		<div class="notice notice-warning" style="margin-top:12px;">
			<p><strong><?php esc_html_e('Diagnostics (admins only)', 'emergencydentalpros'); ?></strong></p>
			<p class="description">
				<?php esc_html_e('Enable with ?edp_seo_debug=1, option edp_seo_debug_panel, or define EDP_SEO_DEBUG in wp-config.php. Use to verify screen hook, columns, and SQL.', 'emergencydentalpros'); ?>
			</p>
			<pre style="max-height:420px;overflow:auto;background:#1d2327;color:#f0f0f1;padding:12px;font-size:12px;"><?php echo esc_html(print_r($edp_debug_data, true)); ?></pre>
		</div>
	<?php endif; ?>

	<?php $table->display(); ?>
</div>
