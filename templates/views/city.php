<?php
/**
 * City template — plugin default.
 * Override in theme: emergencydentalpros/views/city.php
 *
 * @package EmergencyDentalPros
 *
 * @var array<string, mixed> $edp_data
 */

if (!defined('ABSPATH')) {
    exit;
}

$h1               = isset($edp_data['h1']) ? (string) $edp_data['h1'] : '';
$body             = isset($edp_data['body']) ? (string) $edp_data['body'] : '';
$zips             = isset($edp_data['zips']) && is_array($edp_data['zips']) ? $edp_data['zips'] : [];
$nearby           = isset($edp_data['nearby_businesses']) && is_array($edp_data['nearby_businesses']) ? $edp_data['nearby_businesses'] : [];
$communities_h2   = isset($edp_data['communities_h2']) ? (string) $edp_data['communities_h2'] : '';
$communities_body = isset($edp_data['communities_body']) ? (string) $edp_data['communities_body'] : '';
$other_cities_h2  = isset($edp_data['other_cities_h2']) ? (string) $edp_data['other_cities_h2'] : '';
$other_cities     = isset($edp_data['other_cities']) && is_array($edp_data['other_cities']) ? $edp_data['other_cities'] : [];
$row              = isset($edp_data['row']) && is_array($edp_data['row']) ? $edp_data['row'] : [];
$faq              = isset($edp_data['faq']) && is_array($edp_data['faq']) ? $edp_data['faq'] : [];
$faq_enabled      = !empty($faq['enabled']);
$faq_items        = isset($faq['items']) && is_array($faq['items']) ? $faq['items'] : [];
$faq_h2           = isset($faq['h2']) ? (string) $faq['h2'] : '';
$faq_intro        = isset($faq['intro']) ? (string) $faq['intro'] : '';
?>
<main class="edp-seo edp-seo-city" id="edp-seo-main">
	<header class="edp-seo-header">
		<h1 class="edp-seo-h1"><?php echo esc_html($h1); ?></h1>
	</header>
	<div class="edp-seo-body edp-seo-content edp-block-order-body">
		<?php echo wp_kses_post($body); ?>
	</div>
	<?php if ($communities_h2 !== '' || $communities_body !== '') : ?>
		<section class="edp-seo-communities edp-block-order-communities" aria-label="<?php esc_attr_e('Communities we serve', 'emergencydentalpros'); ?>">
			<?php if ($communities_h2 !== '') : ?>
				<h2 class="edp-seo-h2"><?php echo esc_html($communities_h2); ?></h2>
			<?php endif; ?>
			<?php if ($communities_body !== '') : ?>
				<div class="edp-seo-communities-body"><?php echo wp_kses_post($communities_body); ?></div>
			<?php endif; ?>
		</section>
	<?php elseif ($zips !== []) : ?>
		<section class="edp-seo-zips edp-block-order-zips" aria-label="<?php esc_attr_e('Service ZIP codes', 'emergencydentalpros'); ?>">
			<h2 class="edp-seo-h2"><?php esc_html_e('Service ZIP codes', 'emergencydentalpros'); ?></h2>
			<p class="edp-seo-zip-list"><?php echo esc_html(implode(', ', $zips)); ?></p>
		</section>
	<?php endif; ?>

	<?php if ($other_cities !== []) : ?>
		<section class="edp-seo-other-cities edp-block-order-other-cities" aria-label="<?php esc_attr_e('Other cities', 'emergencydentalpros'); ?>">
			<?php if ($other_cities_h2 !== '') : ?>
				<h2 class="edp-seo-h2"><?php echo esc_html($other_cities_h2); ?></h2>
			<?php endif; ?>
			<ul class="edp-cities-grid">
				<?php foreach ($other_cities as $oc) : ?>
					<?php
					if (!is_array($oc)) {
						continue;
					}
					$oc_name       = (string) ($oc['city_name'] ?? '');
					$oc_city_slug  = (string) ($oc['city_slug'] ?? '');
					$oc_state_slug = (string) ($row['state_slug'] ?? '');
					$oc_url        = home_url( user_trailingslashit( 'locations/' . $oc_state_slug . '/' . $oc_city_slug ) );
					?>
					<li class="edp-city-item">
						<a href="<?php echo esc_url($oc_url); ?>"><?php echo esc_html($oc_name); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ($faq_enabled && $faq_items !== []) : ?>
		<section class="ws_faq edp-faq-city edp-block-order-faq" aria-labelledby="edp-faq-heading">
			<div class="ws_shell">
				<?php if ($faq_h2 !== '' || $faq_intro !== '') : ?>
					<header class="ws_section_header">
						<?php if ($faq_h2 !== '') : ?>
							<h2 id="edp-faq-heading" class="ws_section_title"><?php echo esc_html($faq_h2); ?></h2>
						<?php endif; ?>
						<?php if ($faq_intro !== '') : ?>
							<p class="ws_section_intro"><?php echo esc_html($faq_intro); ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>
				<div class="ws_faq__list">
					<?php foreach ($faq_items as $faq_item) :
						if (!is_array($faq_item)) { continue; }
						$fq = (string) ($faq_item['q'] ?? '');
						$fa = (string) ($faq_item['a'] ?? '');
						if ($fq === '') { continue; }
					?>
					<details class="ws_faq__item">
						<summary class="ws_faq__summary">
							<h3 class="ws_faq__question"><?php echo esc_html($fq); ?></h3>
							<svg class="ws_faq__chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
								<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</summary>
						<div class="ws_faq__body">
							<div class="ws_faq__body_inner">
								<p class="ws_faq__answer"><?php echo wp_kses_post($fa); ?></p>
							</div>
						</div>
					</details>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ($nearby !== []) : ?>
		<section class="edp-seo-nearby edp-block-order-nearby" aria-label="<?php esc_attr_e('Related dentists', 'emergencydentalpros'); ?>">
			<h2 class="edp-seo-h2"><?php esc_html_e('Dentists in this area', 'emergencydentalpros'); ?></h2>
			<p class="edp-seo-nearby-note edp-muted">
				<?php esc_html_e('Business listings are shown for convenience and are not endorsements.', 'emergencydentalpros'); ?>
			</p>
			<ul class="edp-dentist-grid">
				<?php foreach ($nearby as $biz) : ?>
					<?php
					if (!is_array($biz)) {
						continue;
					}
					$bname = isset($biz['name']) ? (string) $biz['name'] : '';
					$img = isset($biz['image_url']) ? (string) $biz['image_url'] : '';
					$rating = isset($biz['rating']) ? (float) $biz['rating'] : null;
					$reviews = isset($biz['review_count']) ? (int) $biz['review_count'] : null;
					$phone = isset($biz['phone']) ? (string) $biz['phone'] : '';
					$hours = isset($biz['hours_text']) ? (string) $biz['hours_text'] : '';
					$url = isset($biz['business_url']) ? (string) $biz['business_url'] : '';
					?>
					<li class="edp-dentist-card edp-card">
						<?php if ($img !== '') : ?>
							<div class="edp-dentist-thumb">
								<img src="<?php echo esc_url($img); ?>" alt="" loading="lazy" decoding="async" width="120" height="120" />
							</div>
						<?php endif; ?>
						<div class="edp-dentist-body">
							<?php if ($url !== '') : ?>
								<h3 class="edp-dentist-name">
									<a href="<?php echo esc_url($url); ?>" rel="noopener noreferrer nofollow" target="_blank"><?php echo esc_html($bname); ?></a>
								</h3>
							<?php else : ?>
								<h3 class="edp-dentist-name"><?php echo esc_html($bname); ?></h3>
							<?php endif; ?>
							<?php if ($rating !== null) : ?>
								<p class="edp-dentist-rating">
									<?php
									printf(
										/* translators: 1: rating number, 2: review count */
										esc_html__('Rating %1$s (%2$s reviews)', 'emergencydentalpros'),
										esc_html(number_format_i18n($rating, 1)),
										esc_html($reviews !== null ? (string) $reviews : '—')
									);
									?>
								</p>
							<?php endif; ?>
							<?php
							$digits = $phone !== '' ? preg_replace('/\D+/', '', $phone) : '';
							?>
							<?php if ($phone !== '' && $digits !== '') : ?>
								<p class="edp-dentist-phone">
									<a href="<?php echo esc_url('tel:' . $digits); ?>"><?php echo esc_html($phone); ?></a>
								</p>
							<?php elseif ($phone !== '') : ?>
								<p class="edp-dentist-phone"><?php echo esc_html($phone); ?></p>
							<?php endif; ?>
							<?php if ($hours !== '') : ?>
								<pre class="edp-dentist-hours"><?php echo esc_html($hours); ?></pre>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</main>
