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
