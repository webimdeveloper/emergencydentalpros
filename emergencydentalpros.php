<?php
/**
 * Plugin Name: Local SEO Dental Service Areas
 * Description: Virtual location SEO pages, CSV-backed location database, templates, and schema — without bloating wp_posts.
 * Version: 0.2.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Emergency Dental Pros
 * Text Domain: emergencydentalpros
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EDP_PLUGIN_VERSION', '0.2.1');
define('EDP_PLUGIN_FILE', __FILE__);
define('EDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EDP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EDP_PLUGIN_DIR . 'includes/class-edp-database.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-settings.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-activator.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-rewrite.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-template-engine.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-schema.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-cpt.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-content-resolver.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-importer.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-view-controller.php';
require_once EDP_PLUGIN_DIR . 'includes/class-edp-assets.php';
require_once EDP_PLUGIN_DIR . 'admin/class-edp-admin.php';

register_activation_hook(
    EDP_PLUGIN_FILE,
    static function (): void {
        EDP_Activator::activate();
    }
);

register_deactivation_hook(
    EDP_PLUGIN_FILE,
    static function (): void {
        flush_rewrite_rules(false);
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain('emergencydentalpros', false, dirname(plugin_basename(EDP_PLUGIN_FILE)) . '/languages');

        EDP_Rewrite::register();
        add_action('init', [EDP_CPT::class, 'register'], 5);

        if (is_admin()) {
            EDP_Admin::register();
        }

        EDP_View_Controller::register();
        EDP_Assets::register();
    },
    10
);

if (defined('WP_CLI') && WP_CLI) {
    require_once EDP_PLUGIN_DIR . 'includes/cli/class-edp-cli.php';
}
