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

$contexts = [
	'states_index' => __('States Index', 'emergencydentalpros'),
	'state_cities' => __('State + Cities', 'emergencydentalpros'),
	'city_landing' => __('City Landing', 'emergencydentalpros'),
];

$context_vars = [
	'states_index' => '{site_name}',
	'state_cities' => '{state_name}, {state_short}, {state_slug}, {site_name}',
	'city_landing' => '{city_name}, {state_name}, {state_short}, {county_name}, {main_zip}, {list_of_related_zips}, {site_name}',
];

?>
<div id="edp-tpl-wrap" class="wrap">
	<h1><?php esc_html_e('Local SEO — Templates', 'emergencydentalpros'); ?></h1>
	<p class="edp-subtitle"><?php esc_html_e('SEO meta, headings, and content templates for each page type.', 'emergencydentalpros'); ?></p>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if (isset($_GET['updated'])) : ?>
		<div class="edp-notice edp-notice-success"><?php esc_html_e('Settings saved.', 'emergencydentalpros'); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('edp_seo_save_settings', 'edp_seo_nonce'); ?>
		<input type="hidden" name="action" value="edp_seo_save_settings" />

		<?php /* ---- Settings + Docs row ---- */ ?>
		<div class="edp-stat-row">
			<div class="edp-card" style="margin-bottom:0;">
				<div class="edp-card-header"><?php esc_html_e('Settings', 'emergencydentalpros'); ?></div>
				<div class="edp-card-body">
					<div class="edp-form-row">
						<label for="edp_og_image_url"><?php esc_html_e('Default OG image URL', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[og_image_url]" type="text" id="edp_og_image_url"
							value="<?php echo esc_attr((string) ($settings['og_image_url'] ?? '')); ?>"
							placeholder="https://example.com/og-image.jpg" />
						<p class="edp-hint"><?php esc_html_e('Output as og:image and twitter:card image on all location pages. Recommended: 1200×630 px.', 'emergencydentalpros'); ?></p>
					</div>
					<div class="edp-form-row">
						<label for="edp_twitter_site"><?php esc_html_e('Twitter / X handle', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[twitter_site]" type="text" id="edp_twitter_site"
							value="<?php echo esc_attr((string) ($settings['twitter_site'] ?? '')); ?>"
							placeholder="@YourHandle" />
						<p class="edp-hint"><?php esc_html_e('Output as twitter:site meta tag. Include or omit the @ — it will be normalised.', 'emergencydentalpros'); ?></p>
					</div>
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
			</div>
		</div>

		<?php /* ---- Tabbed template sections ---- */ ?>
		<div class="edp-tpl-layout">
		<div class="edp-tpl-main">
		<div class="edp-card">
			<div class="edp-tabs-nav" role="tablist">
				<?php foreach ($contexts as $key => $label) : ?>
					<button
						type="button"
						class="edp-tab-btn<?php echo $key === 'states_index' ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $key === 'states_index' ? 'true' : 'false'; ?>"
						aria-controls="edp-panel-<?php echo esc_attr($key); ?>"
						data-tab="<?php echo esc_attr($key); ?>"
					><?php echo esc_html($label); ?></button>
				<?php endforeach; ?>
			</div>

			<?php foreach ($contexts as $key => $label) :
				$t = isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : [];
			?>
				<div
					id="edp-panel-<?php echo esc_attr($key); ?>"
					class="edp-tab-panel<?php echo $key === 'states_index' ? ' is-active' : ''; ?>"
					role="tabpanel"
					aria-labelledby="edp-tab-<?php echo esc_attr($key); ?>"
				>
					<p class="edp-hint" style="margin-top:0; margin-bottom:12px;">
						<?php esc_html_e('Available variables:', 'emergencydentalpros'); ?>
						<span class="edp-hint-vars"><?php echo esc_html($context_vars[$key]); ?></span>
					</p>

					<div class="edp-form-row">
						<label for="edp_<?php echo esc_attr($key); ?>_mt"><?php esc_html_e('Meta title', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][meta_title]" type="text"
							id="edp_<?php echo esc_attr($key); ?>_mt"
							value="<?php echo esc_attr((string) ($t['meta_title'] ?? '')); ?>" />
					</div>

					<div class="edp-form-row">
						<label for="edp_<?php echo esc_attr($key); ?>_md"><?php esc_html_e('Meta description', 'emergencydentalpros'); ?></label>
						<textarea name="edp_seo[templates][<?php echo esc_attr($key); ?>][meta_description]"
							rows="3" id="edp_<?php echo esc_attr($key); ?>_md"><?php echo esc_textarea((string) ($t['meta_description'] ?? '')); ?></textarea>
					</div>

					<div class="edp-form-row">
						<label for="edp_<?php echo esc_attr($key); ?>_h1"><?php esc_html_e('H1', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][h1]" type="text"
							id="edp_<?php echo esc_attr($key); ?>_h1"
							value="<?php echo esc_attr((string) ($t['h1'] ?? '')); ?>" />
					</div>

					<div class="edp-form-row">
						<label for="edp_<?php echo esc_attr($key); ?>_subtitle"><?php esc_html_e('Subtitle', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][subtitle]" type="text"
							id="edp_<?php echo esc_attr($key); ?>_subtitle"
							value="<?php echo esc_attr((string) ($t['subtitle'] ?? '')); ?>" />
						<p class="edp-hint"><?php esc_html_e('Short description shown below the page title in the hero.', 'emergencydentalpros'); ?></p>
					</div>

					<div class="edp-form-row">
						<label><?php esc_html_e('Content (HTML)', 'emergencydentalpros'); ?></label>
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
					</div>

					<?php if ($key === 'city_landing') : ?>
						<hr class="edp-divider" />

						<div class="edp-form-row">
							<label><?php esc_html_e('Communities section — Text (HTML)', 'emergencydentalpros'); ?></label>
							<?php
							wp_editor(
								(string) ($t['communities_body'] ?? ''),
								'edp_communities_body_' . $key,
								[
									'textarea_name' => 'edp_seo[templates][' . $key . '][communities_body]',
									'media_buttons' => false,
									'textarea_rows' => 5,
								]
							);
							?>
							<p class="edp-hint"><?php esc_html_e('Variables: {city_name}, {county_name}, {state_name}, {state_short}, {list_of_related_zips}, {main_zip}', 'emergencydentalpros'); ?></p>
						</div>

						<hr class="edp-divider" />

						<div class="edp-form-row">
							<label for="edp_city_landing_faq_intro"><?php esc_html_e('FAQ section — Short description', 'emergencydentalpros'); ?></label>
							<input name="edp_seo[templates][city_landing][faq_intro]" type="text"
								id="edp_city_landing_faq_intro"
								value="<?php echo esc_attr((string) ($t['faq_intro'] ?? '')); ?>" />
							<p class="edp-hint"><?php esc_html_e('Variables: {city_name}, {state_name}, {state_short}', 'emergencydentalpros'); ?></p>
						</div>

						<div class="edp-form-row">
							<label><?php esc_html_e('FAQ Items', 'emergencydentalpros'); ?></label>
							<p class="edp-hint" style="margin-bottom:10px;"><?php esc_html_e('These items appear on every dynamic city page. Static pages can override with unique content.', 'emergencydentalpros'); ?></p>
							<div id="edp-faq-items-list" class="edp-faq-items-list">
								<?php
								$faq_items_saved = isset($t['faq_items']) && is_array($t['faq_items']) ? $t['faq_items'] : [];
								foreach ($faq_items_saved as $idx => $faq_item) :
									$fq = (string) ($faq_item['q'] ?? '');
									$fa = (string) ($faq_item['a'] ?? '');
								?>
								<div class="edp-faq-item" data-index="<?php echo (int) $idx; ?>">
									<div class="edp-faq-item-fields">
										<input type="text" class="edp-faq-q" placeholder="<?php esc_attr_e('Question', 'emergencydentalpros'); ?>" value="<?php echo esc_attr($fq); ?>" />
										<textarea class="edp-faq-a" rows="3" placeholder="<?php esc_attr_e('Answer', 'emergencydentalpros'); ?>"><?php echo esc_textarea($fa); ?></textarea>
									</div>
									<button type="button" class="edp-faq-delete-btn" title="<?php esc_attr_e('Delete item', 'emergencydentalpros'); ?>">&#x2715;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" id="edp-faq-add-btn" class="edp-btn edp-btn-secondary" style="margin-top:10px;">
								+ <?php esc_html_e('Add FAQ Item', 'emergencydentalpros'); ?>
							</button>
							<input type="hidden" name="edp_seo[templates][city_landing][faq_items]" id="edp_faq_items_json" value="<?php echo esc_attr(wp_json_encode($faq_items_saved)); ?>" />
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div><!-- /.edp-card -->
		</div><!-- /.edp-tpl-main -->

		<?php /* ---- Aside: Business listing settings ---- */ ?>
		<?php
		$gs = isset($settings['global_settings']) && is_array($settings['global_settings']) ? $settings['global_settings'] : [];
		?>
		<aside class="edp-tpl-aside" aria-label="<?php esc_attr_e('Business listing settings', 'emergencydentalpros'); ?>">
			<div class="edp-tpl-aside-panel is-active" id="edp-aside-biz">
				<div class="edp-tpl-aside-header">
					<span class="edp-tpl-aside-title"><?php esc_html_e('Business Listing', 'emergencydentalpros'); ?></span>
					<span class="edp-tpl-aside-badge edp-badge--green"><?php esc_html_e('Google', 'emergencydentalpros'); ?></span>
				</div>
				<div class="edp-tpl-aside-body">

					<div class="edp-form-row">
						<label for="edp_biz_name"><?php esc_html_e('Business name', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][biz_name]" type="text" id="edp_biz_name"
							value="<?php echo esc_attr((string) ($gs['biz_name'] ?? '')); ?>"
							placeholder="Emergency Dental Pros" />
						<p class="edp-hint"><?php esc_html_e('Shown as the pinned first item in the city page business list.', 'emergencydentalpros'); ?></p>
					</div>

					<div class="edp-form-row">
						<label for="edp_phone_text"><?php esc_html_e('Phone (display)', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][phone_text]" type="text" id="edp_phone_text"
							value="<?php echo esc_attr((string) ($gs['phone_text'] ?? '(855) 407-7377')); ?>"
							placeholder="(855) 407-7377" />
					</div>

					<div class="edp-form-row">
						<label for="edp_phone_href"><?php esc_html_e('Phone (href)', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][phone_href]" type="text" id="edp_phone_href"
							value="<?php echo esc_attr((string) ($gs['phone_href'] ?? 'tel:8554077377')); ?>"
							placeholder="tel:8554077377" />
					</div>

					<div class="edp-form-row">
						<label for="edp_opening_hours"><?php esc_html_e('Opening hours', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][opening_hours]" type="text" id="edp_opening_hours"
							value="<?php echo esc_attr((string) ($gs['opening_hours'] ?? '24/7')); ?>"
							placeholder="24/7" />
					</div>

					<div class="edp-form-row">
						<label for="edp_rating_score"><?php esc_html_e('Rating score (0–5)', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][rating_score]" type="text" id="edp_rating_score"
							value="<?php echo esc_attr((string) ($gs['rating_score'] ?? '4.9')); ?>"
							placeholder="4.9" style="max-width:80px;" />
					</div>

					<div class="edp-form-row">
						<label for="edp_rating_count"><?php esc_html_e('Review count', 'emergencydentalpros'); ?></label>
						<input name="edp_seo[global_settings][rating_count]" type="text" id="edp_rating_count"
							value="<?php echo esc_attr((string) ($gs['rating_count'] ?? '127')); ?>"
							placeholder="127" style="max-width:80px;" />
					</div>

					<div class="edp-form-row">
						<label><?php esc_html_e('Featured image', 'emergencydentalpros'); ?></label>
						<?php $feat_url = (string) ($gs['featured_img_url'] ?? ''); ?>
						<?php if ($feat_url !== '') : ?>
							<img class="edp-media-preview" src="<?php echo esc_url($feat_url); ?>" alt="" />
						<?php endif; ?>
						<div class="edp-media-row">
							<input name="edp_seo[global_settings][featured_img_url]" type="text"
								id="edp_featured_img_url"
								value="<?php echo esc_attr($feat_url); ?>"
								placeholder="https://..." />
							<button type="button" class="edp-media-btn button" data-target="edp_featured_img_url">
								<?php esc_html_e('Choose image', 'emergencydentalpros'); ?>
							</button>
						</div>
						<p class="edp-hint"><?php esc_html_e('Logo or photo shown in the pinned business card.', 'emergencydentalpros'); ?></p>
					</div>

					<div class="edp-form-row">
						<label><?php esc_html_e('Rating avatars (collage)', 'emergencydentalpros'); ?></label>
						<?php $avatars_url = (string) ($gs['rating_avatars_url'] ?? ''); ?>
						<?php if ($avatars_url !== '') : ?>
							<img class="edp-media-preview" src="<?php echo esc_url($avatars_url); ?>" alt="" />
						<?php endif; ?>
						<div class="edp-media-row">
							<input name="edp_seo[global_settings][rating_avatars_url]" type="text"
								id="edp_rating_avatars_url"
								value="<?php echo esc_attr($avatars_url); ?>"
								placeholder="https://..." />
							<button type="button" class="edp-media-btn button" data-target="edp_rating_avatars_url">
								<?php esc_html_e('Choose image', 'emergencydentalpros'); ?>
							</button>
						</div>
						<p class="edp-hint"><?php esc_html_e('Single collage image shown below the star rating.', 'emergencydentalpros'); ?></p>
					</div>

				</div>
			</div>
		</aside>

		</div><!-- /.edp-tpl-layout -->

		<?php /* ── Page Cache settings (inside the main save form) ── */ ?>
		<?php $pc = $settings['page_cache'] ?? []; ?>
		<div class="edp-card" style="margin-top:24px;">
			<div class="edp-card-header"><?php esc_html_e('Page Cache', 'emergencydentalpros'); ?></div>
			<div class="edp-card-body">
				<p style="margin-top:0; color:#6b7280; font-size:13px;">
					<?php esc_html_e('Caches the full HTML of each city landing page. Logged-in users always bypass the cache. Cache is cleared automatically whenever you save settings.', 'emergencydentalpros'); ?>
				</p>
				<div class="edp-form-row" style="flex-direction:row; align-items:center; gap:12px;">
					<label for="edp_pc_enabled" style="font-weight:600; white-space:nowrap; margin-bottom:0;">
						<?php esc_html_e('Enable page cache', 'emergencydentalpros'); ?>
					</label>
					<input type="hidden" name="edp_seo[page_cache][enabled]" value="0">
					<input type="checkbox" id="edp_pc_enabled"
						name="edp_seo[page_cache][enabled]" value="1"
						<?php checked(!empty($pc['enabled'])); ?>>
				</div>
				<div class="edp-form-row">
					<label for="edp_pc_ttl"><?php esc_html_e('Cache lifetime', 'emergencydentalpros'); ?></label>
					<select name="edp_seo[page_cache][ttl]" id="edp_pc_ttl" style="max-width:200px;">
						<?php foreach ([1 => '1 hour', 6 => '6 hours', 12 => '12 hours', 24 => '24 hours', 72 => '3 days', 168 => '7 days'] as $h => $label): ?>
							<option value="<?php echo esc_attr((string) $h); ?>" <?php selected((int)($pc['ttl'] ?? 24), $h); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="edp-hint"><?php esc_html_e('How long a cached page is served before the next visitor regenerates it.', 'emergencydentalpros'); ?></p>
				</div>
			</div>
		</div>

		<div class="edp-btn-row">
			<button type="submit" class="edp-btn edp-btn-primary"><?php esc_html_e('Save', 'emergencydentalpros'); ?></button>
		</div>
	</form>

	<?php /* ── Cache management — separate form, outside the main form ── */ ?>
	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (isset($_GET['cache_cleared'])): ?>
		<div class="edp-notice edp-notice-success" style="margin-top:16px;">
			<?php esc_html_e('Cache cleared.', 'emergencydentalpros'); ?>
		</div>
	<?php endif; ?>

	<?php $cached_pages = EDP_Cache::get_cached_pages(); ?>
	<div class="edp-card" style="margin-top:24px;">
		<div class="edp-card-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
			<span>
				<?php esc_html_e('Cached Pages', 'emergencydentalpros'); ?>
				<span style="font-weight:400; font-size:12px; color:#6b7280; margin-left:6px;">
					(<?php echo esc_html((string) count($cached_pages)); ?>)
				</span>
			</span>
			<?php if ($cached_pages !== []): ?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
					<?php wp_nonce_field('edp_clear_page_cache', '_wpnonce', true, true); ?>
					<input type="hidden" name="action" value="edp_clear_page_cache">
					<button type="submit" class="edp-btn" style="font-size:12px; padding:4px 12px;">
						<?php esc_html_e('Clear all', 'emergencydentalpros'); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<div class="edp-card-body" style="padding:0;">
			<?php if ($cached_pages === []): ?>
				<p style="padding:16px; margin:0; color:#6b7280; font-size:13px;">
					<?php esc_html_e('No pages cached yet. Enable the cache and visit a city page to populate it.', 'emergencydentalpros'); ?>
				</p>
			<?php else: ?>
				<table class="widefat fixed striped" style="border:none; border-radius:0;">
					<thead>
						<tr>
							<th><?php esc_html_e('URL', 'emergencydentalpros'); ?></th>
							<th style="width:140px;"><?php esc_html_e('Cached', 'emergencydentalpros'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Size', 'emergencydentalpros'); ?></th>
							<th style="width:80px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($cached_pages as $cp): ?>
							<tr>
								<td>
									<a href="<?php echo esc_url(home_url($cp['url'])); ?>" target="_blank" rel="noopener">
										<?php echo esc_html($cp['url']); ?>
									</a>
								</td>
								<td style="color:#6b7280; font-size:12px;">
									<?php echo esc_html(human_time_diff($cp['time']) . ' ago'); ?>
								</td>
								<td style="color:#6b7280; font-size:12px;">
									<?php echo esc_html(number_format($cp['size'] / 1024, 1) . ' KB'); ?>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
										<?php wp_nonce_field('edp_clear_page_cache_one', '_wpnonce', true, true); ?>
										<input type="hidden" name="action" value="edp_clear_page_cache_one">
										<input type="hidden" name="cache_path" value="<?php echo esc_attr($cp['url']); ?>">
										<button type="submit" class="button button-small">
											<?php esc_html_e('Clear', 'emergencydentalpros'); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
(function () {
	var tabs   = document.querySelectorAll('#edp-tpl-wrap .edp-tab-btn');
	var panels = document.querySelectorAll('#edp-tpl-wrap .edp-tab-panel');

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			var target = tab.dataset.tab;

			tabs.forEach(function (t) {
				t.classList.remove('is-active');
				t.setAttribute('aria-selected', 'false');
			});
			panels.forEach(function (p) { p.classList.remove('is-active'); });

			tab.classList.add('is-active');
			tab.setAttribute('aria-selected', 'true');

			var panel = document.getElementById('edp-panel-' + target);
			if (panel) {
				panel.classList.add('is-active');

				/* Re-init any TinyMCE editors in this panel so they render correctly */
				if (typeof tinymce !== 'undefined') {
					panel.querySelectorAll('.wp-editor-area').forEach(function (el) {
						var id = el.id;
						if (tinymce.get(id)) {
							tinymce.get(id).show();
						}
					});
				}
			}
		});
	});
})();

/* ── Media picker ── */
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.edp-media-btn').forEach(function (btn) {
		var frame = null;

		btn.addEventListener('click', function () {
			if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
				alert('WordPress media library is not available. Reload the page and try again.');
				return;
			}

			if (frame) { frame.open(); return; }

			var targetId = btn.dataset.target;
			var input    = document.getElementById(targetId);

			frame = wp.media({ title: 'Choose image', library: { type: 'image' }, multiple: false });

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				if (input) { input.value = att.url; }

				var row = btn.closest('.edp-form-row');
				if (!row) { return; }
				var preview = row.querySelector('.edp-media-preview');
				if (preview) {
					preview.src = att.url;
				} else {
					var img      = document.createElement('img');
					img.className = 'edp-media-preview';
					img.src      = att.url;
					img.alt      = '';
					row.insertBefore(img, btn.closest('.edp-media-row'));
				}
			});

			frame.open();
		});
	});
});

/* ── FAQ repeater (settings page) ── */
(function () {
	var list    = document.getElementById('edp-faq-items-list');
	var addBtn  = document.getElementById('edp-faq-add-btn');
	var jsonIn  = document.getElementById('edp_faq_items_json');
	var form    = jsonIn ? jsonIn.closest('form') : null;

	if (!list || !addBtn || !jsonIn) { return; }

	function makeItem(q, a) {
		var wrap = document.createElement('div');
		wrap.className = 'edp-faq-item';
		wrap.innerHTML =
			'<div class="edp-faq-item-fields">'
			+ '<input type="text" class="edp-faq-q" placeholder="Question" value="' + escAttr(q) + '" />'
			+ '<textarea class="edp-faq-a" rows="3" placeholder="Answer">' + escHtml(a) + '</textarea>'
			+ '</div>'
			+ '<button type="button" class="edp-faq-delete-btn" title="Delete">&#x2715;</button>';

		wrap.querySelector('.edp-faq-delete-btn').addEventListener('click', function () {
			wrap.remove();
		});
		return wrap;
	}

	function escAttr(s) {
		return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}
	function escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	/* Attach delete to pre-rendered items */
	list.querySelectorAll('.edp-faq-delete-btn').forEach(function (btn) {
		btn.addEventListener('click', function () { btn.closest('.edp-faq-item').remove(); });
	});

	addBtn.addEventListener('click', function () {
		list.appendChild(makeItem('', ''));
		list.lastElementChild.querySelector('.edp-faq-q').focus();
	});

	/* Serialize to JSON before submit */
	if (form) {
		form.addEventListener('submit', function () {
			var items = [];
			list.querySelectorAll('.edp-faq-item').forEach(function (row) {
				var q = (row.querySelector('.edp-faq-q').value || '').trim();
				var a = (row.querySelector('.edp-faq-a').value || '').trim();
				if (q !== '') { items.push({ q: q, a: a }); }
			});
			jsonIn.value = JSON.stringify(items);
		});
	}
})();
</script>
