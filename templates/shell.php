<?php
/**
 * Document shell for virtual SEO routes.
 * Loads theme header/footer so the theme stylesheet applies.
 * The view template is resolved from theme override or plugin default.
 *
 * @package EmergencyDentalPros
 *
 * @var string $edp_view  state-list|state|city
 * @var array<string, mixed> $edp_data
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$tpl = EDP_View_Controller::resolve_template($edp_view);

if (is_readable($tpl)) {
    include $tpl;
}

get_footer();
