<?php
/**
 * Template settings (three contexts).
 *
 * @package EmergencyDentalPros
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $templates
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap">
	<h1><?php esc_html_e('Local SEO — Templates', 'emergencydentalpros'); ?></h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'emergencydentalpros'); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('edp_seo_save_settings', 'edp_seo_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_save_settings" />

		<h2><?php esc_html_e('Business', 'emergencydentalpros'); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="edp_business_name"><?php esc_html_e('Business name (schema)', 'emergencydentalpros'); ?></label></th>
				<td>
					<input name="edp_seo[business_name]" type="text" id="edp_business_name" class="regular-text"
						value="<?php echo esc_attr((string) ($settings['business_name'] ?? '')); ?>" />
				</td>
			</tr>
		</table>

		<?php
		$contexts = [
			'states_index' => __('States index (/locations/)', 'emergencydentalpros'),
			'state_cities' => __('State + cities list (/locations/{state}/)', 'emergencydentalpros'),
			'city_landing' => __('City landing page', 'emergencydentalpros'),
		];

		foreach ($contexts as $key => $label) :
			$t = isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : [];
			?>
			<hr />
			<h2><?php echo esc_html($label); ?></h2>
			<p class="description">
				<?php
				if ($key === 'city_landing') {
					esc_html_e('Variables: {city_name}, {state_name}, {state_short}, {list_of_related_zips}, {site_name}', 'emergencydentalpros');
				} elseif ($key === 'state_cities') {
					esc_html_e('Variables: {state_name}, {state_short}, {state_slug}, {site_name}', 'emergencydentalpros');
				} else {
					esc_html_e('Variables: {site_name}', 'emergencydentalpros');
				}
				?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="edp_<?php echo esc_attr($key); ?>_mt"><?php esc_html_e('Meta title', 'emergencydentalpros'); ?></label></th>
					<td>
						<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][meta_title]" type="text" class="large-text" id="edp_<?php echo esc_attr($key); ?>_mt"
							value="<?php echo esc_attr((string) ($t['meta_title'] ?? '')); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="edp_<?php echo esc_attr($key); ?>_md"><?php esc_html_e('Meta description', 'emergencydentalpros'); ?></label></th>
					<td>
						<textarea name="edp_seo[templates][<?php echo esc_attr($key); ?>][meta_description]" class="large-text" rows="3" id="edp_<?php echo esc_attr($key); ?>_md"><?php echo esc_textarea((string) ($t['meta_description'] ?? '')); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="edp_<?php echo esc_attr($key); ?>_h1"><?php esc_html_e('H1', 'emergencydentalpros'); ?></label></th>
					<td>
						<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][h1]" type="text" class="large-text" id="edp_<?php echo esc_attr($key); ?>_h1"
							value="<?php echo esc_attr((string) ($t['h1'] ?? '')); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="edp_<?php echo esc_attr($key); ?>_body"><?php esc_html_e('Content (HTML)', 'emergencydentalpros'); ?></label></th>
					<td>
						<?php
						wp_editor(
							(string) ($t['body'] ?? ''),
							'edp_body_' . $key,
							[
								'textarea_name' => 'edp_seo[templates][' . $key . '][body]',
								'media_buttons' => true,
								'textarea_rows' => 8,
							]
						);
						?>
					</td>
				</tr>
			</table>
			<?php
		endforeach;
		?>

		<?php submit_button(__('Save', 'emergencydentalpros')); ?>
	</form>
</div>
