<?php
/**
 * Plugin Name: Emergency Dental Pros
 * Description: Core plugin for Emergency Dental Pros custom features.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Emergency Dental Pros
 * Text Domain: emergencydentalpros
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EDP_PLUGIN_VERSION', '0.1.0');
define('EDP_PLUGIN_FILE', __FILE__);
define('EDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EDP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EDP_PLUGIN_DIR . 'includes/class-edp-assets.php';

add_action('plugins_loaded', static function () {
    EDP_Assets::register();
});
