<?php
/**
 * City landing template (MVP blocks: H1, body, zips).
 *
 * @package EmergencyDentalPros
 *
 * @var array<string, mixed> $edp_data
 */

if (!defined('ABSPATH')) {
    exit;
}

$h1 = isset($edp_data['h1']) ? (string) $edp_data['h1'] : '';
$body = isset($edp_data['body']) ? (string) $edp_data['body'] : '';
$zips = isset($edp_data['zips']) && is_array($edp_data['zips']) ? $edp_data['zips'] : [];
$nearby = isset($edp_data['nearby_businesses']) && is_array($edp_data['nearby_businesses']) ? $edp_data['nearby_businesses'] : [];
?>
<main class="edp-seo edp-seo-city" id="edp-seo-main">
	<header class="edp-seo-header">
		<h1 class="edp-seo-h1"><?php echo esc_html($h1); ?></h1>
	</header>
	<div class="edp-seo-body edp-seo-content edp-block-order-body">
		<?php echo wp_kses_post($body); ?>
	</div>
	<?php if ($zips !== []) : ?>
		<section class="edp-seo-zips edp-block-order-zips" aria-label="<?php esc_attr_e('Service ZIP codes', 'emergencydentalpros'); ?>">
			<h2 class="edp-seo-h2"><?php esc_html_e('Service ZIP codes', 'emergencydentalpros'); ?></h2>
			<p class="edp-seo-zip-list"><?php echo esc_html(implode(', ', $zips)); ?></p>
		</section>
	<?php endif; ?>

	<?php if ($nearby !== []) : ?>
		<section class="edp-seo-nearby edp-block-order-nearby" aria-label="<?php esc_attr_e('Related dentists', 'emergencydentalpros'); ?>">
			<h2 class="edp-seo-h2"><?php esc_html_e('Dentists in this area', 'emergencydentalpros'); ?></h2>
			<p class="edp-seo-nearby-note edp-muted">
				<?php esc_html_e('Information from Yelp is shown for convenience. Listings are not endorsements.', 'emergencydentalpros'); ?>
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
			<p class="edp-seo-yelp-attribution">
				<a href="https://www.yelp.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Powered by Yelp', 'emergencydentalpros'); ?></a>
			</p>
		</section>
	<?php endif; ?>
</main>
