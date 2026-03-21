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
</main>
