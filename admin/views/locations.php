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
 * @var array<string, mixed>|null  $edp_yelp_notice
 */

if (!defined('ABSPATH')) {
	exit;
}

$edp_seo_debug = $edp_seo_debug ?? false;
$edp_debug_data = $edp_debug_data ?? [];
$edp_yelp_notice = isset($edp_yelp_notice) && is_array($edp_yelp_notice) ? $edp_yelp_notice : null;

?>
<style>
	.edp-yelp-status--ok { display: inline-flex; align-items: center; vertical-align: middle; }
	.edp-yelp-dot { display: inline-block; width: 10px; height: 10px; border-radius: 9999px; background: #16a34a; box-shadow: 0 0 0 1px rgba(0,0,0,.08); }
	.edp-yelp-fetch-one { display: inline-block; margin: 0; }
</style>
<div class="wrap">
	<h1><?php esc_html_e('Local SEO — Locations', 'emergencydentalpros'); ?></h1>

	<?php if (isset($_GET['yelp_none'])) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e('No locations were selected for Yelp.', 'emergencydentalpros'); ?></p></div>
	<?php endif; ?>

	<?php if ($edp_yelp_notice !== null) : ?>
		<?php
		$proc = (int) ($edp_yelp_notice['processed'] ?? 0);
		$calls = (int) ($edp_yelp_notice['api_calls'] ?? 0);
		$msgs = isset($edp_yelp_notice['messages']) && is_array($edp_yelp_notice['messages']) ? $edp_yelp_notice['messages'] : [];
		$yelp_ok = !empty($edp_yelp_notice['ok']);
		$yelp_err = isset($edp_yelp_notice['error']) ? (string) $edp_yelp_notice['error'] : '';
		$has403 = false;

		foreach ($msgs as $m) {
			if (is_string($m) && str_contains($m, '(403)')) {
				$has403 = true;
				break;
			}
		}

		$notice_class = 'info';

		if (!$yelp_ok && $yelp_err === 'missing_api_key') {
			$notice_class = 'error';
		} elseif ($msgs === [] && $yelp_ok) {
			$notice_class = 'success';
		} elseif ($msgs !== [] || !$yelp_ok) {
			$notice_class = 'warning';
		}
		?>
		<div class="notice notice-<?php echo esc_attr($notice_class); ?> is-dismissible">
			<?php if (!$yelp_ok && $yelp_err === 'missing_api_key') : ?>
				<p><?php esc_html_e('Yelp API key is missing. Add it under Local SEO → Import (Yelp section) or define EDP_YELP_API_KEY in wp-config.php.', 'emergencydentalpros'); ?></p>
			<?php else : ?>
			<p>
				<?php
				printf(
					/* translators: 1: locations processed, 2: API calls */
					esc_html__('Yelp fetch finished — locations processed: %1$d — API calls (approx.): %2$d', 'emergencydentalpros'),
					$proc,
					$calls
				);
				?>
			</p>
			<?php endif; ?>
			<?php if ($has403) : ?>
				<p>
					<?php esc_html_e('HTTP 403 from Yelp usually means the API key is invalid, the app is restricted, or the Fusion API is not enabled for this key. Regenerate the key in the Yelp developer dashboard and save it under Import → Yelp.', 'emergencydentalpros'); ?>
				</p>
			<?php endif; ?>
			<?php if ($msgs !== []) : ?>
				<ul style="list-style:disc;margin-left:1.25em;">
					<?php foreach (array_slice($msgs, 0, 15) as $m) : ?>
						<li><code><?php echo esc_html((string) $m); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
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
				<span style="color:#b32d2e;"><?php esc_html_e('not found — use Import and choose your CSV file, or add raw_data.csv on the server.', 'emergencydentalpros'); ?></span>
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
