<?php
/**
 * Plugin-only document shell for virtual SEO routes (not theme templates).
 *
 * @package EmergencyDentalPros
 *
 * @var string $edp_view states_index|state_cities|city_landing
 * @var array<string, mixed> $edp_data
 */

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('edp-seo-virtual'); ?>>
<?php
if ($edp_view === 'states_index') {
    include EDP_PLUGIN_DIR . 'templates/views/states-index.php';
} elseif ($edp_view === 'state_cities') {
    include EDP_PLUGIN_DIR . 'templates/views/state-cities.php';
} elseif ($edp_view === 'city_landing') {
    include EDP_PLUGIN_DIR . 'templates/views/city-landing.php';
}
wp_footer();
?>
</body>
</html>
