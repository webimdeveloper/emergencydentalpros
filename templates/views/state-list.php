<?php
/**
 * State list template — plugin default.
 * Override in theme: emergencydentalpros/views/state-list.php
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
$states = isset($edp_data['states']) && is_array($edp_data['states']) ? $edp_data['states'] : [];
?>
<main class="edp-seo edp-seo-states" id="edp-seo-main">
	<header class="edp-seo-header">
		<h1 class="edp-seo-h1"><?php echo esc_html($h1); ?></h1>
	</header>
	<div class="edp-seo-body edp-seo-content">
		<?php echo wp_kses_post($body); ?>
	</div>
	<?php if ($states !== []) : ?>
		<nav class="edp-seo-state-list" aria-label="<?php esc_attr_e('States', 'emergencydentalpros'); ?>">
			<ul>
				<?php foreach ($states as $st) : ?>
					<?php
					$slug = isset($st['state_slug']) ? sanitize_title((string) $st['state_slug']) : '';
					$name = isset($st['state_name']) ? (string) $st['state_name'] : $slug;
					if ($slug === '') {
						continue;
					}
					$url = home_url(user_trailingslashit('locations/' . rawurlencode($slug)));
					?>
					<li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</main>
