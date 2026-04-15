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
        add_action('admin_post_edp_seo_save_google', [self::class, 'handle_save_google']);
        add_action('admin_post_edp_seo_google_import', [self::class, 'handle_google_import']);
        add_action('admin_post_edp_seo_google_test', [self::class, 'handle_google_test']);
        add_action('admin_post_edp_seo_google_fetch_single', [self::class, 'handle_google_fetch_single']);
        add_action('admin_init', [self::class, 'maybe_bulk_fetch_google'], 20);
        add_action('admin_post_edp_seo_location_action', [self::class, 'handle_location_action']);
        add_action('wp_ajax_edp_google_import_step', [self::class, 'ajax_google_import_step']);
    }

    public static function menus(): void
    {
        add_menu_page(
            __('Local SEO Locations', 'emergencydentalpros'),
            __('Local SEO', 'emergencydentalpros'),
            'manage_options',
            'edp-seo',
            [self::class, 'render_settings'],
            'dashicons-medical',
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
        $path = '';
        $cleanup_temp = null;
        $upload_label = '';

        $file = isset($_FILES['edp_csv_file']) && is_array($_FILES['edp_csv_file']) ? $_FILES['edp_csv_file'] : null;

        if ($file !== null && !empty($file['name'])) {
            $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

            if ($err !== UPLOAD_ERR_OK) {
                if ($err === UPLOAD_ERR_NO_FILE) {
                    // Treat as "no upload" and fall through to server default below.
                } else {
                    $reason = self::map_php_upload_error_code($err);
                    wp_safe_redirect(
                        admin_url('admin.php?page=edp-seo-import&import_error=upload&reason=' . rawurlencode($reason))
                    );
                    exit;
                }
            } else {
                $original_name = isset($file['name']) ? (string) $file['name'] : '';

                if ($original_name === '' || !preg_match('/\.csv$/i', $original_name)) {
                    wp_safe_redirect(admin_url('admin.php?page=edp-seo-import&import_error=upload_wp'));
                    exit;
                }

                if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                    wp_safe_redirect(admin_url('admin.php?page=edp-seo-import&import_error=upload_wp'));
                    exit;
                }

                $tmp = trailingslashit(get_temp_dir()) . 'edp-seo-' . wp_generate_password(16, false, false) . '.csv';

                if (!@move_uploaded_file($file['tmp_name'], $tmp)) {
                    wp_safe_redirect(admin_url('admin.php?page=edp-seo-import&import_error=upload&reason=write'));
                    exit;
                }

                $path = $tmp;
                $cleanup_temp = $path;
                $upload_label = 'upload:' . sanitize_file_name($original_name);
            }
        }

        if ($path === '') {
            $path = $default_path;
        }

        $result = null;

        try {
            $result = EDP_Importer::import_from_csv_file($path);
        } finally {
            if ($cleanup_temp !== null && is_string($cleanup_temp) && $cleanup_temp !== '' && is_file($cleanup_temp)) {
                wp_delete_file($cleanup_temp);
            }
        }

        if ($upload_label !== '' && is_array($result)) {
            $result['path'] = $upload_label;
        }

        $log = [
            'at' => time(),
            'path' => is_array($result) ? ($result['path'] ?? $path) : $path,
            'ok' => is_array($result) && empty($result['error']),
            'error' => is_array($result) && isset($result['error']) ? (string) $result['error'] : '',
            'rows' => is_array($result) ? (int) ($result['rows'] ?? 0) : 0,
            'skipped' => is_array($result) ? (int) ($result['skipped'] ?? 0) : 0,
            'groups' => is_array($result) ? (int) ($result['groups'] ?? 0) : 0,
        ];

        update_option(self::OPTION_IMPORT_LOG, $log, false);

        if (is_array($result)) {
            set_transient(
                'edp_seo_last_import',
                $result,
                MINUTE_IN_SECONDS * 30
            );
        }

        $query = ['imported' => '1'];

        if (is_array($result) && !empty($result['error'])) {
            $query['import_error'] = '1';
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php?page=edp-seo-import')));
        exit;
    }

    private static function map_php_upload_error_code(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'form';
            case UPLOAD_ERR_PARTIAL:
                return 'partial';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'tmp';
            case UPLOAD_ERR_CANT_WRITE:
                return 'write';
            case UPLOAD_ERR_EXTENSION:
                return 'ext';
            default:
                return 'unknown';
        }
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

        $uid = get_current_user_id();
        $edp_yelp_notice = get_transient('edp_seo_google_locations_notice_' . $uid);

        if (is_array($edp_yelp_notice)) {
            delete_transient('edp_seo_google_locations_notice_' . $uid);
        } else {
            $edp_yelp_notice = null;
        }

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

    /**
     * Bulk action: Fetch Google Places (GET request from WP_List_Table).
     */
    public static function maybe_bulk_fetch_google(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_REQUEST['page']) ? sanitize_key((string) wp_unslash($_REQUEST['page'])) : '';

        if ($page !== 'edp-seo-locations') {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once EDP_PLUGIN_DIR . 'admin/class-edp-locations-list-table.php';

        $table = new EDP_Locations_List_Table('');
        $action = $table->current_action();

        if ($action !== 'fetch_google') {
            return;
        }

        check_admin_referer('bulk-locations');

        $ids = isset($_REQUEST['location']) ? array_map('intval', (array) wp_unslash($_REQUEST['location'])) : [];
        $ids = array_values(array_filter($ids));

        if ($ids === []) {
            wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_none=1'));
            exit;
        }

        $result = EDP_Google_Places_Importer::import_for_location_ids($ids, null);

        set_transient(
            'edp_seo_google_locations_notice_' . get_current_user_id(),
            $result,
            120
        );

        wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_bulk=1'));
        exit;
    }

    public static function handle_google_fetch_single(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_google_single', 'edp_seo_google_single_nonce');

        $id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;

        if ($id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_none=1'));
            exit;
        }

        $result = EDP_Google_Places_Importer::import_for_location_ids([$id], null);

        set_transient(
            'edp_seo_google_locations_notice_' . get_current_user_id(),
            $result,
            120
        );

        wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_single=1'));
        exit;
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

    public static function handle_save_google(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_save_google', 'edp_seo_google_save_nonce');

        $raw = isset($_POST['edp_google']) && is_array($_POST['edp_google']) ? wp_unslash($_POST['edp_google']) : [];

        EDP_Google_Places_Config::save(
            [
                'api_key'      => isset($raw['api_key']) ? (string) $raw['api_key'] : '',
                'term'         => isset($raw['term']) ? (string) $raw['term'] : 'emergency dentist',
                'limit'        => isset($raw['limit']) ? (int) $raw['limit'] : 5,
                'fetch_details' => isset($raw['fetch_details']) && (string) $raw['fetch_details'] === '1',
            ]
        );

        wp_safe_redirect(
            add_query_arg('google_saved', '1', admin_url('admin.php?page=edp-seo-import'))
        );
        exit;
    }

    public static function handle_google_import(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_google_import', 'edp_seo_google_import_nonce');

        $offset = isset($_POST['google_offset']) ? (int) $_POST['google_offset'] : 0;
        $limit  = isset($_POST['google_limit'])  ? (int) $_POST['google_limit']  : 25;
        $offset = max(0, $offset);
        $limit  = max(1, min(300, $limit));

        $fetch_details = isset($_POST['google_fetch_details']) && (string) $_POST['google_fetch_details'] === '1';

        $result = EDP_Google_Places_Importer::import_batch($offset, $limit, $fetch_details);

        set_transient(
            'edp_seo_last_google_import',
            $result,
            MINUTE_IN_SECONDS * 30
        );

        $args = ['page' => 'edp-seo-import', 'google_imported' => '1'];

        if (empty($result['ok'])) {
            $args['google_error'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_google_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_seo_google_test', 'edp_seo_google_test_nonce');

        $result = EDP_Google_Places_Importer::test_api_connection();

        set_transient(
            'edp_seo_google_test_' . get_current_user_id(),
            $result,
            120
        );

        wp_safe_redirect(admin_url('admin.php?page=edp-seo-import'));
        exit;
    }

    /**
     * AJAX: process one city from the Google Places batch.
     * Called repeatedly by the JS progress loop — one request per city avoids gateway timeouts.
     */
    public static function ajax_google_import_step(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('edp_google_import_step', 'nonce');

        $offset        = max(0, (int) ($_POST['offset'] ?? 0));
        $step          = max(0, (int) ($_POST['step'] ?? 0));
        $total         = max(1, min(300, (int) ($_POST['total'] ?? 1)));
        $fetch_details = !empty($_POST['fetch_details']);

        $result = EDP_Google_Places_Importer::import_batch($offset + $step, 1, $fetch_details);

        $next_step = $step + 1;
        $done      = $next_step >= $total || (int) ($result['processed'] ?? 0) === 0;

        wp_send_json_success([
            'step'      => $next_step,
            'done'      => $done,
            'api_calls' => (int) ($result['api_calls'] ?? 0),
            'messages'  => $result['messages'] ?? [],
        ]);
    }
}
