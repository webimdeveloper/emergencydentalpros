<?php
/**
 * State template — plugin default.
 * Override in theme: emergencydentalpros/views/state.php
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
$cities = isset($edp_data['cities']) && is_array($edp_data['cities']) ? $edp_data['cities'] : [];
$state = isset($edp_data['state']) && is_array($edp_data['state']) ? $edp_data['state'] : [];
$state_slug = isset($state['state_slug']) ? sanitize_title((string) $state['state_slug']) : '';
?>
<main class="edp-seo edp-seo-state-cities" id="edp-seo-main">
	<header class="edp-seo-header">
		<h1 class="edp-seo-h1"><?php echo esc_html($h1); ?></h1>
	</header>
	<div class="edp-seo-body edp-seo-content">
		<?php echo wp_kses_post($body); ?>
	</div>
	<?php if ($cities !== []) : ?>
		<nav class="edp-seo-city-list" aria-label="<?php esc_attr_e('Cities', 'emergencydentalpros'); ?>">
			<ul>
				<?php foreach ($cities as $city) : ?>
					<?php
					$cs = isset($city['city_slug']) ? sanitize_title((string) $city['city_slug']) : '';
					$cn = isset($city['city_name']) ? (string) $city['city_name'] : $cs;
					if ($cs === '' || $state_slug === '') {
						continue;
					}
					$url = EDP_Rewrite::city_url( $city );
					?>
					<li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($cn); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</main>
