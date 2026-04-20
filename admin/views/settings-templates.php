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

		<?php /* ---- Settings card (business + social) ---- */ ?>
		<div class="edp-card">
			<div class="edp-card-header"><?php esc_html_e('Settings', 'emergencydentalpros'); ?></div>
			<div class="edp-card-body">
				<div class="edp-form-row">
					<label for="edp_business_name"><?php esc_html_e('Business name', 'emergencydentalpros'); ?></label>
					<input name="edp_seo[business_name]" type="text" id="edp_business_name"
						value="<?php echo esc_attr((string) ($settings['business_name'] ?? '')); ?>" />
					<p class="edp-hint"><?php esc_html_e('Used in LocalBusiness / Dentist JSON-LD schema output on every city page.', 'emergencydentalpros'); ?></p>
				</div>
				<hr class="edp-divider" />
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
						<p class="edp-hint"><?php esc_html_e('Section heading displayed below H1 on the page.', 'emergencydentalpros'); ?></p>
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
							<label for="edp_<?php echo esc_attr($key); ?>_communities_h2"><?php esc_html_e('Communities section — H2', 'emergencydentalpros'); ?></label>
							<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][communities_h2]" type="text"
								id="edp_<?php echo esc_attr($key); ?>_communities_h2"
								value="<?php echo esc_attr((string) ($t['communities_h2'] ?? '')); ?>" />
							<p class="edp-hint"><?php esc_html_e('Variables: {city_name}, {county_name}, {state_name}, {state_short}', 'emergencydentalpros'); ?></p>
						</div>

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

						<div class="edp-form-row">
							<label for="edp_<?php echo esc_attr($key); ?>_other_cities_h2"><?php esc_html_e('Other cities section — H2', 'emergencydentalpros'); ?></label>
							<input name="edp_seo[templates][<?php echo esc_attr($key); ?>][other_cities_h2]" type="text"
								id="edp_<?php echo esc_attr($key); ?>_other_cities_h2"
								value="<?php echo esc_attr((string) ($t['other_cities_h2'] ?? '')); ?>" />
							<p class="edp-hint"><?php esc_html_e('Variables: {state_name}, {state_short}', 'emergencydentalpros'); ?></p>
						</div>

						<hr class="edp-divider" />

						<div class="edp-form-row">
							<label for="edp_city_landing_faq_h2"><?php esc_html_e('FAQ section — H2', 'emergencydentalpros'); ?></label>
							<input name="edp_seo[templates][city_landing][faq_h2]" type="text"
								id="edp_city_landing_faq_h2"
								value="<?php echo esc_attr((string) ($t['faq_h2'] ?? '')); ?>" />
							<p class="edp-hint"><?php esc_html_e('Variables: {city_name}, {state_name}, {state_short}', 'emergencydentalpros'); ?></p>
						</div>

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

		<?php /* ---- Aside: per-tab analytics / info panel (reserved for future content) ---- */ ?>
		<aside class="edp-tpl-aside" aria-label="<?php esc_attr_e('Template analytics', 'emergencydentalpros'); ?>">
			<?php foreach ($contexts as $key => $label) : ?>
			<div class="edp-tpl-aside-panel<?php echo $key === 'states_index' ? ' is-active' : ''; ?>"
				id="edp-aside-<?php echo esc_attr($key); ?>"
				data-aside="<?php echo esc_attr($key); ?>">
				<div class="edp-tpl-aside-header">
					<span class="edp-tpl-aside-title"><?php echo esc_html($label); ?></span>
					<span class="edp-tpl-aside-badge"><?php esc_html_e('Analytics', 'emergencydentalpros'); ?></span>
				</div>
				<div class="edp-tpl-aside-body">
					<p class="edp-tpl-aside-empty">
						<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
						<?php esc_html_e('Analytics and insights for this template will appear here.', 'emergencydentalpros'); ?>
					</p>
				</div>
			</div>
			<?php endforeach; ?>
		</aside>

		</div><!-- /.edp-tpl-layout -->

		<div class="edp-btn-row">
			<button type="submit" class="edp-btn edp-btn-primary"><?php esc_html_e('Save', 'emergencydentalpros'); ?></button>
		</div>
	</form>
</div>

<script>
(function () {
	var tabs        = document.querySelectorAll('#edp-tpl-wrap .edp-tab-btn');
	var panels      = document.querySelectorAll('#edp-tpl-wrap .edp-tab-panel');
	var asidePanels = document.querySelectorAll('#edp-tpl-wrap .edp-tpl-aside-panel');

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			var target = tab.dataset.tab;

			tabs.forEach(function (t) {
				t.classList.remove('is-active');
				t.setAttribute('aria-selected', 'false');
			});
			panels.forEach(function (p) { p.classList.remove('is-active'); });
			asidePanels.forEach(function (p) { p.classList.remove('is-active'); });

			tab.classList.add('is-active');
			tab.setAttribute('aria-selected', 'true');

			var panel = document.getElementById('edp-panel-' + target);
			if (panel) {
				panel.classList.add('is-active');

			var aside = document.getElementById('edp-aside-' + target);
			if (aside) { aside.classList.add('is-active'); }

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
