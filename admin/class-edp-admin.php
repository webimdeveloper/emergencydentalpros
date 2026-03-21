<?php
/**
 * Admin menus and screens.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Admin
{
    public const OPTION_IMPORT_LOG = 'edp_seo_import_log';

    /**
     * Return value of add_submenu_page for Locations — required for WP_List_Table screen.
     *
     * @var string
     */
    public static $locations_screen_hook = '';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menus']);
        add_action('admin_post_edp_seo_save_settings', [self::class, 'handle_save_settings']);
        add_action('admin_post_edp_seo_import', [self::class, 'handle_import']);
        add_action('admin_post_edp_seo_location_action', [self::class, 'handle_location_action']);
    }

    public static function menus(): void
    {
        add_menu_page(
            __('Local SEO Locations', 'emergencydentalpros'),
            __('Local SEO', 'emergencydentalpros'),
            'manage_options',
            'edp-seo',
            [self::class, 'render_settings'],
            'dashicons-location-alt',
            58
        );

        add_submenu_page(
            'edp-seo',
            __('Templates', 'emergencydentalpros'),
            __('Templates', 'emergencydentalpros'),
            'manage_options',
            'edp-seo',
            [self::class, 'render_settings']
        );

        add_submenu_page(
            'edp-seo',
            __('Import', 'emergencydentalpros'),
            __('Import', 'emergencydentalpros'),
            'manage_options',
            'edp-seo-import',
            [self::class, 'render_import']
        );

        add_submenu_page(
            'edp-seo',
            __('Locations', 'emergencydentalpros'),
            __('Locations', 'emergencydentalpros'),
            'manage_options',
            'edp-seo-locations',
            [self::class, 'render_locations']
        );
    }

    public static function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = EDP_Settings::get_all();
        $templates = $settings['templates'] ?? [];

        require EDP_PLUGIN_DIR . 'admin/views/settings-templates.php';
    }

    public static function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_save_settings', 'edp_seo_nonce');

        $raw = wp_unslash($_POST['edp_seo'] ?? []);

        if (!is_array($raw)) {
            $raw = [];
        }

        $merged = EDP_Settings::get_all();
        $merged['business_name'] = isset($raw['business_name']) ? sanitize_text_field((string) $raw['business_name']) : '';

        foreach (['states_index', 'state_cities', 'city_landing'] as $ctx) {
            if (!isset($raw['templates'][$ctx]) || !is_array($raw['templates'][$ctx])) {
                continue;
            }

            $t = $raw['templates'][$ctx];
            $merged['templates'][$ctx]['meta_title'] = (string) ($t['meta_title'] ?? '');
            $merged['templates'][$ctx]['meta_description'] = (string) ($t['meta_description'] ?? '');
            $merged['templates'][$ctx]['h1'] = (string) ($t['h1'] ?? '');
            $merged['templates'][$ctx]['body'] = (string) ($t['body'] ?? '');
        }

        EDP_Settings::save($merged);

        wp_safe_redirect(
            add_query_arg(
                'updated',
                '1',
                admin_url('admin.php?page=edp-seo')
            )
        );
        exit;
    }

    public static function render_import(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require EDP_PLUGIN_DIR . 'admin/views/import.php';
    }

    public static function handle_import(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_import', 'edp_seo_import_nonce');

        $default_path = EDP_PLUGIN_DIR . 'raw_data.csv';
        $path = $default_path;

        $custom_raw = isset($_POST['edp_csv_path']) && is_string($_POST['edp_csv_path'])
            ? sanitize_text_field(wp_unslash($_POST['edp_csv_path']))
            : '';

        if ($custom_raw !== '') {
            if (!is_readable($custom_raw)) {
                update_option(
                    self::OPTION_IMPORT_LOG,
                    [
                        'at' => time(),
                        'path' => $custom_raw,
                        'ok' => false,
                        'error' => 'custom_path_not_readable',
                        'rows' => 0,
                        'skipped' => 0,
                        'groups' => 0,
                    ],
                    false
                );

                wp_safe_redirect(admin_url('admin.php?page=edp-seo-import&import_error=custom_path'));
                exit;
            }

            $path = $custom_raw;
        }

        $result = EDP_Importer::import_from_csv_file($path);

        $log = [
            'at' => time(),
            'path' => $result['path'] ?? $path,
            'ok' => empty($result['error']),
            'error' => isset($result['error']) ? (string) $result['error'] : '',
            'rows' => (int) ($result['rows'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'groups' => (int) ($result['groups'] ?? 0),
        ];

        update_option(self::OPTION_IMPORT_LOG, $log, false);

        set_transient(
            'edp_seo_last_import',
            $result,
            MINUTE_IN_SECONDS * 30
        );

        $query = ['imported' => '1'];

        if (!empty($result['error'])) {
            $query['import_error'] = '1';
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php?page=edp-seo-import')));
        exit;
    }

    /**
     * Show the Locations diagnostics panel (admins only).
     */
    public static function is_locations_debug_visible(): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        if (isset($_GET['edp_seo_debug']) && (string) $_GET['edp_seo_debug'] === '1') {
            return true;
        }

        if ((bool) get_option('edp_seo_debug_panel', false)) {
            return true;
        }

        if (defined('EDP_SEO_DEBUG') && EDP_SEO_DEBUG) {
            return true;
        }

        return (bool) apply_filters('edp_seo_show_locations_debug', false);
    }

    public static function render_locations(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once EDP_PLUGIN_DIR . 'admin/class-edp-locations-list-table.php';

        global $wpdb;

        $screen_hook = self::$locations_screen_hook;

        if ($screen_hook === '' && function_exists('get_current_screen')) {
            $screen = get_current_screen();

            if ($screen instanceof WP_Screen) {
                $screen_hook = (string) $screen->id;
            }
        }

        $table = new EDP_Locations_List_Table($screen_hook);
        $table->prepare_items();

        $location_count = EDP_Database::count_rows();
        $default_csv = EDP_PLUGIN_DIR . 'raw_data.csv';
        $default_csv_ok = is_readable($default_csv);
        $import_log = get_option(self::OPTION_IMPORT_LOG, []);

        if (!is_array($import_log)) {
            $import_log = [];
        }

        $edp_seo_debug = self::is_locations_debug_visible();
        $edp_debug_data = [];

        if ($edp_seo_debug) {
            $current = function_exists('get_current_screen') ? get_current_screen() : null;

            $edp_debug_data = [
                'plugin_version' => defined('EDP_PLUGIN_VERSION') ? EDP_PLUGIN_VERSION : '',
                'php_version' => PHP_VERSION,
                'locations_screen_hook' => self::$locations_screen_hook,
                'screen_hook_passed_to_list_table' => $screen_hook,
                'get_current_screen' => $current instanceof WP_Screen
                    ? [
                        'id' => $current->id,
                        'base' => $current->base,
                        'parent_file' => $current->parent_file,
                    ]
                    : null,
                'wpdb_last_error_global' => (string) $wpdb->last_error,
                'column_info' => $table->get_resolved_column_info(),
                'list_table_screen' => $table->get_debug_screen_info(),
                'query' => [
                    'last_sql' => $table->debug_last_sql,
                    'last_db_error' => $table->debug_last_db_error,
                    'rows_returned' => $table->debug_rows_returned,
                    'total_count' => $table->debug_total_count,
                ],
                'first_item_keys' => !empty($table->items[0]) && is_array($table->items[0])
                    ? array_keys($table->items[0])
                    : [],
                'pagination' => [
                    'paged' => isset($_GET['paged']) ? (int) $_GET['paged'] : 1,
                ],
            ];
        }

        require EDP_PLUGIN_DIR . 'admin/views/locations.php';
    }

    public static function handle_location_action(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_location_action', 'edp_seo_location_nonce');

        $action = isset($_POST['edp_action']) ? sanitize_key((string) $_POST['edp_action']) : '';
        $id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;

        if ($id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations'));
            exit;
        }

        global $wpdb;

        $table = EDP_Database::table_name();

        if ($action === 'map_post') {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

            if ($post_id > 0 && get_post($post_id)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->update(
                    $table,
                    [
                        'custom_post_id' => $post_id,
                        'override_type' => 'mapped',
                    ],
                    ['id' => $id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        } elseif ($action === 'create_cpt') {
            $row = EDP_Database::get_row_by_id($id);

            if ($row !== null) {
                $settings = EDP_Settings::get_all();
                $tpl = $settings['templates']['city_landing'] ?? [];
                $base = EDP_Template_Engine::base_vars();
                $vars = EDP_Template_Engine::context_from_city_row($base, $row);
                $title = EDP_Template_Engine::replace((string) ($tpl['meta_title'] ?? ''), $vars);
                $body = EDP_Template_Engine::replace((string) ($tpl['body'] ?? ''), $vars);

                $post_id = wp_insert_post(
                    [
                        'post_type' => EDP_CPT::POST_TYPE,
                        'post_status' => 'publish',
                        'post_title' => $title,
                        'post_content' => $body,
                    ],
                    true
                );

                if (!is_wp_error($post_id) && $post_id > 0) {
                    update_post_meta((int) $post_id, '_edp_location_id', $id);

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->update(
                        $table,
                        [
                            'custom_post_id' => (int) $post_id,
                            'override_type' => 'cpt',
                        ],
                        ['id' => $id],
                        ['%d', '%s'],
                        ['%d']
                    );
                }
            }
        } elseif ($action === 'clear_override') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET custom_post_id = NULL, override_type = NULL WHERE id = %d",
                    $id
                )
            );
        }

        wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations'));
        exit;
    }
}
