<?php

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Assets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
    }

    public static function enqueue_frontend(): void
    {
        $view = (string) get_query_var(EDP_Rewrite::Q_VIEW);

        if ($view === '') {
            return;
        }

        $asset_rel_path = 'assets/main.css';
        $asset_abs_path = EDP_PLUGIN_DIR . $asset_rel_path;
        $asset_url = EDP_PLUGIN_URL . $asset_rel_path;

        if (file_exists($asset_abs_path)) {
            wp_enqueue_style(
                'edp-main',
                $asset_url,
                [],
                (string) filemtime($asset_abs_path)
            );
        }
    }

    public static function enqueue_admin(): void
    {
        $screen = get_current_screen();
        if ($screen && str_contains((string) $screen->id, 'edp-seo')) {
            wp_enqueue_media();
        }

        $css_path = EDP_PLUGIN_DIR . 'admin/css/edp-admin.css';
        $css_url  = EDP_PLUGIN_URL . 'admin/css/edp-admin.css';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'edp-admin',
                $css_url,
                [],
                (string) filemtime($css_path)
            );
        }

        $asset_rel_path = 'assets/main.js';
        $asset_abs_path = EDP_PLUGIN_DIR . $asset_rel_path;
        $asset_url = EDP_PLUGIN_URL . $asset_rel_path;

        if (file_exists($asset_abs_path)) {
            wp_enqueue_script(
                'edp-main',
                $asset_url,
                [],
                (string) filemtime($asset_abs_path),
                true
            );
        }
    }
}
