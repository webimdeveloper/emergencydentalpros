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
    public const OPTION_SHEET_URL  = 'edp_sheet_url';

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
        add_action('admin_post_edp_seo_save_google', [self::class, 'handle_save_google']);
        add_action('admin_post_edp_seo_google_import', [self::class, 'handle_google_import']);
        add_action('admin_post_edp_seo_google_test', [self::class, 'handle_google_test']);
        add_action('admin_post_edp_seo_google_fetch_single', [self::class, 'handle_google_fetch_single']);
        add_action('admin_post_edp_seo_location_action', [self::class, 'handle_location_action']);
        add_action('wp_ajax_edp_google_import_step', [self::class, 'ajax_google_import_step']);
        add_action('wp_ajax_edp_google_fetch_location', [self::class, 'ajax_google_fetch_location']);
        add_action('wp_ajax_edp_google_delete_location', [self::class, 'ajax_google_delete_location']);
        add_action('admin_post_edp_sheet_save_url', [self::class, 'handle_sheet_save_url']);
        add_action('admin_post_edp_sheet_sa_save', [self::class, 'handle_sheet_sa_save']);
        add_action('admin_post_edp_sheet_sa_clear', [self::class, 'handle_sheet_sa_clear']);
        add_action('wp_ajax_edp_sheet_sync_v2', [self::class, 'ajax_sheet_sync_v2']);
        add_action('wp_ajax_edp_save_post_mapping', [self::class, 'ajax_save_post_mapping']);
        add_action('wp_ajax_edp_clear_override', [self::class, 'ajax_clear_override']);
        add_action('wp_ajax_edp_create_location_page', [self::class, 'ajax_create_location_page']);
        add_action('wp_ajax_edp_delete_location_row', [self::class, 'ajax_delete_location_row']);
        add_action('wp_ajax_edp_delete_all_rows', [self::class, 'ajax_delete_all_rows']);
        add_action('wp_ajax_edp_check_pagespeed', [self::class, 'ajax_check_pagespeed']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_ajax_edp_dev_seed_csv', [self::class, 'ajax_dev_seed_csv']);
        }
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

        $locations_hook = add_submenu_page(
            'edp-seo',
            __('Locations', 'emergencydentalpros'),
            __('Locations', 'emergencydentalpros'),
            'manage_options',
            'edp-seo-locations',
            [self::class, 'render_locations']
        );

        self::$locations_screen_hook = (string) $locations_hook;

        // load-{hook} fires after the page is identified but before output — safe to redirect.
        if ($locations_hook) {
            add_action('load-' . $locations_hook, [self::class, 'maybe_bulk_fetch_google']);
        }
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

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array values sanitized individually below.
        $raw = wp_unslash( $_POST['edp_seo'] ?? [] );

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
            $merged['templates'][$ctx]['meta_title']       = (string) ($t['meta_title'] ?? '');
            $merged['templates'][$ctx]['meta_description'] = (string) ($t['meta_description'] ?? '');
            $merged['templates'][$ctx]['h1']               = (string) ($t['h1'] ?? '');
            $merged['templates'][$ctx]['subtitle']         = (string) ($t['subtitle'] ?? '');
            $merged['templates'][$ctx]['body']             = (string) ($t['body'] ?? '');

            if ($ctx === 'city_landing') {
                $merged['templates'][$ctx]['communities_h2']   = (string) ($t['communities_h2'] ?? '');
                $merged['templates'][$ctx]['communities_body'] = (string) ($t['communities_body'] ?? '');
                $merged['templates'][$ctx]['other_cities_h2']  = (string) ($t['other_cities_h2'] ?? '');
            }
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

    /**
     * Save the uploaded service-account JSON key file.
     */
    public static function handle_sheet_sa_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_sheet_sa_save', 'edp_sheet_sa_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated via is_uploaded_file() below.
        $file = isset($_FILES['edp_sa_json']) && is_array($_FILES['edp_sa_json']) ? $_FILES['edp_sa_json'] : null;

        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg('sa_error', 'upload', admin_url('admin.php?page=edp-seo-import')));
            exit;
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_safe_redirect(add_query_arg('sa_error', 'upload', admin_url('admin.php?page=edp-seo-import')));
            exit;
        }

        $json = file_get_contents($file['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ($json === false || $json === '') {
            wp_safe_redirect(add_query_arg('sa_error', 'empty', admin_url('admin.php?page=edp-seo-import')));
            exit;
        }

        $result = EDP_Sheet_Credentials::save_from_json($json);

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg(
                ['sa_error' => 'parse', 'sa_msg' => rawurlencode($result->get_error_message())],
                admin_url('admin.php?page=edp-seo-import')
            ));
            exit;
        }

        // Clear any cached token — credentials changed.
        EDP_Sheet_API::clear_token_cache();

        wp_safe_redirect(add_query_arg('sa_saved', '1', admin_url('admin.php?page=edp-seo-import')));
        exit;
    }

    /**
     * Remove saved service-account credentials.
     */
    public static function handle_sheet_sa_clear(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_sheet_sa_clear', 'edp_sheet_sa_clear_nonce');

        EDP_Sheet_Credentials::clear();
        EDP_Sheet_API::clear_token_cache();

        wp_safe_redirect(add_query_arg('sa_cleared', '1', admin_url('admin.php?page=edp-seo-import')));
        exit;
    }

    /**
     * AJAX: run the two-way Google Sheets sync (v2 — uses Sheets API + writes back).
     */
    public static function ajax_sheet_sync_v2(): void
    {
        check_ajax_referer('edp_sheet_sync_v2', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        if (!EDP_Sheet_Credentials::is_configured()) {
            wp_send_json_error(['message' => esc_html__('Service account credentials not configured. Upload the JSON key file first.', 'emergencydentalpros')]);
        }

        $url = (string) get_option(self::OPTION_SHEET_URL, '');

        if ($url === '') {
            wp_send_json_error(['message' => esc_html__('No Google Sheets URL saved. Enter and save a URL first.', 'emergencydentalpros')]);
        }

        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        @set_time_limit(120);

        $result = EDP_Sheet_Sync::run($url);

        $log = array_merge($result, ['at' => time(), 'ok' => empty($result['error'])]);
        update_option(self::OPTION_IMPORT_LOG, $log, false);

        if (!empty($result['error'])) {
            wp_send_json_error(['message' => (string) $result['error'], 'result' => $result]);
        }

        wp_send_json_success($result);
    }

    /**
     * Save the Google Sheets URL entered on the Import screen.
     */
    public static function handle_sheet_save_url(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'emergencydentalpros'));
        }

        check_admin_referer('edp_sheet_save_url', 'edp_sheet_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below via esc_url_raw.
        $raw = isset($_POST['edp_sheet_url']) ? wp_unslash((string) $_POST['edp_sheet_url']) : '';
        $url = esc_url_raw(trim($raw));

        update_option(self::OPTION_SHEET_URL, $url, false);

        wp_safe_redirect(
            add_query_arg('sheet_saved', '1', admin_url('admin.php?page=edp-seo-import'))
        );
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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only debug flag, admin-only.
        if ( isset( $_GET['edp_seo_debug'] ) && '1' === sanitize_key( wp_unslash( $_GET['edp_seo_debug'] ) ) ) {
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
        $edp_google_notice = get_transient('edp_seo_google_locations_notice_' . $uid);

        if (is_array($edp_google_notice)) {
            delete_transient('edp_seo_google_locations_notice_' . $uid);
        } else {
            $edp_google_notice = null;
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
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- diagnostics read-only param.
                    'paged' => isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1,
                ],
            ];
        }

        require EDP_PLUGIN_DIR . 'admin/views/locations.php';
    }

    /**
     * Bulk actions dispatcher — hooked to load-{locations_hook} so it only runs on this page,
     * before any output is sent, making wp_safe_redirect() safe to call.
     */
    public static function maybe_bulk_fetch_google(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once EDP_PLUGIN_DIR . 'admin/class-edp-locations-list-table.php';

        $table  = new EDP_Locations_List_Table('');
        $action = $table->current_action();

        if ($action === 'fetch_google') {
            self::bulk_fetch_google();
        } elseif ($action === 'create_pages') {
            self::bulk_create_pages();
        } elseif ($action === 'delete_rows') {
            self::bulk_delete_rows();
        }
    }

    private static function bulk_fetch_google(): void
    {
        check_admin_referer('bulk-locations');

        $ids = isset($_REQUEST['location'])
            ? array_values(array_filter(array_map('intval', (array) wp_unslash($_REQUEST['location']))))
            : [];

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

    private static function bulk_create_pages(): void
    {
        check_admin_referer('bulk-locations');

        $ids = isset($_REQUEST['location'])
            ? array_values(array_filter(array_map('intval', (array) wp_unslash($_REQUEST['location']))))
            : [];

        if ($ids === []) {
            wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_none=1'));
            exit;
        }

        $settings = EDP_Settings::get_all();
        $tpl      = $settings['templates']['city_landing'] ?? [];
        $base     = EDP_Template_Engine::base_vars();
        $created  = 0;
        $skipped  = 0;

        global $wpdb;
        $db_table = EDP_Database::table_name();

        foreach ($ids as $id) {
            $row = EDP_Database::get_row_by_id($id);

            if ($row === null) {
                $skipped++;
                continue;
            }

            // Skip rows that already have a CPT override.
            if ((string) ($row['override_type'] ?? '') === 'cpt' && (int) ($row['custom_post_id'] ?? 0) > 0) {
                $skipped++;
                continue;
            }

            $vars  = EDP_Template_Engine::context_from_city_row($base, $row);
            $title = EDP_Template_Engine::replace((string) ($tpl['meta_title'] ?? ''), $vars);
            $body  = EDP_Template_Engine::replace((string) ($tpl['body'] ?? ''), $vars);

            $post_id = wp_insert_post(
                [
                    'post_type'    => EDP_CPT::POST_TYPE,
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_content' => $body,
                ],
                true
            );

            if (!is_wp_error($post_id) && $post_id > 0) {
                update_post_meta((int) $post_id, '_edp_location_id', $id);
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->update(
                    $db_table,
                    ['custom_post_id' => (int) $post_id, 'override_type' => 'cpt'],
                    ['id' => $id],
                    ['%d', '%s'],
                    ['%d']
                );
                $created++;
            } else {
                $skipped++;
            }
        }

        wp_safe_redirect(add_query_arg(
            ['pages_created' => $created, 'pages_skipped' => $skipped],
            admin_url('admin.php?page=edp-seo-locations')
        ));
        exit;
    }

    private static function bulk_delete_rows(): void
    {
        check_admin_referer('bulk-locations');

        $ids = isset($_REQUEST['location'])
            ? array_values(array_filter(array_map('intval', (array) wp_unslash($_REQUEST['location']))))
            : [];

        if ($ids === []) {
            wp_safe_redirect(admin_url('admin.php?page=edp-seo-locations&google_none=1'));
            exit;
        }

        global $wpdb;
        $table   = EDP_Database::table_name();
        $deleted = 0;

        foreach ($ids as $id) {
            $row = EDP_Database::get_row_by_id($id);
            if ($row !== null) {
                $post_id = (int) ($row['custom_post_id'] ?? 0);
                if ($post_id > 0 && (string) ($row['override_type'] ?? '') === 'cpt') {
                    wp_delete_post($post_id, true);
                }
            }

            EDP_Database::delete_nearby_for_location($id);
            $result = $wpdb->delete($table, ['id' => $id], ['%d']);

            if ($result !== false) {
                $deleted++;
            }
        }

        wp_safe_redirect(add_query_arg(
            ['rows_deleted' => $deleted],
            admin_url('admin.php?page=edp-seo-locations')
        ));
        exit;
    }

    /**
     * AJAX: delete every row in the locations table plus linked CPT posts and Google data.
     */
    public static function ajax_delete_all_rows(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        check_ajax_referer('edp_delete_all_rows', 'nonce');

        global $wpdb;
        $table = EDP_Database::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ids = $wpdb->get_col("SELECT id FROM {$table}");
        $deleted = 0;

        foreach ($ids as $id) {
            $id  = (int) $id;
            $row = EDP_Database::get_row_by_id($id);

            if ($row !== null) {
                $post_id = (int) ($row['custom_post_id'] ?? 0);
                if ($post_id > 0 && (string) ($row['override_type'] ?? '') === 'cpt') {
                    wp_delete_post($post_id, true);
                }
            }

            EDP_Database::delete_nearby_for_location($id);

            if ($wpdb->delete($table, ['id' => $id], ['%d']) !== false) {
                $deleted++;
            }
        }

        wp_send_json_success(['deleted' => $deleted]);
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

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array values sanitized individually below.
        $raw = isset( $_POST['edp_google'] ) && is_array( $_POST['edp_google'] ) ? wp_unslash( $_POST['edp_google'] ) : [];

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
     * Build the HTML for a single row's listing status cell.
     * Used both by the list table column and by the AJAX handlers to return updated HTML.
     */
    public static function build_listing_cell_html(int $location_id, int $count): string
    {
        $nonce = wp_create_nonce('edp_google_location_actions');

        if ($count > 0) {
            return sprintf(
                '<div class="edp-listing-cell">'
                    . '<span class="edp-listing-badge edp-listing-badge--has">'
                    . '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> %1$d'
                    . '</span>'
                    . '<span class="edp-listing-btns">'
                    . '<button type="button" class="button button-small edp-listing-btn" '
                    .     'data-location-id="%2$d" data-listing-action="fetch" data-nonce="%3$s" '
                    .     'title="%4$s">'
                    .     '<span class="dashicons dashicons-update" aria-hidden="true"></span>'
                    . '</button>'
                    . '<button type="button" class="button button-small edp-listing-btn edp-listing-btn--danger" '
                    .     'data-location-id="%2$d" data-listing-action="delete" data-nonce="%3$s" '
                    .     'title="%5$s">'
                    .     '<span class="dashicons dashicons-trash" aria-hidden="true"></span>'
                    . '</button>'
                    . '</span>'
                . '</div>',
                $count,
                $location_id,
                esc_attr($nonce),
                esc_attr__('Update listings', 'emergencydentalpros'),
                esc_attr__('Delete all listings', 'emergencydentalpros')
            );
        }

        return sprintf(
            '<div class="edp-listing-cell">'
                . '<span class="edp-listing-badge edp-listing-badge--empty">'
                . '<span class="dashicons dashicons-minus" aria-hidden="true"></span>'
                . '</span>'
                . '<span class="edp-listing-btns">'
                . '<button type="button" class="button button-small edp-listing-btn" '
                .     'data-location-id="%1$d" data-listing-action="fetch" data-nonce="%2$s" '
                .     'title="%3$s">'
                .     '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>'
                . '</button>'
                . '</span>'
            . '</div>',
            $location_id,
            esc_attr($nonce),
            esc_attr__('Fetch listings', 'emergencydentalpros')
        );
    }

    /**
     * AJAX: fetch Google listings for a single location and return updated cell HTML.
     */
    public static function ajax_google_fetch_location(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('edp_google_location_actions', 'nonce');

        $id = max(0, absint( wp_unslash( $_POST['location_id'] ?? 0 ) ) );

        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid location ID']);
        }

        $result   = EDP_Google_Places_Importer::import_for_location_ids([$id], null);
        $map      = EDP_Database::get_nearby_status_for_locations([$id]);
        $count    = (int) ($map[$id] ?? 0);

        wp_send_json_success([
            'html'      => self::build_listing_cell_html($id, $count),
            'count'     => $count,
            'api_calls' => (int) ($result['api_calls'] ?? 0),
            'messages'  => $result['messages'] ?? [],
        ]);
    }

    /**
     * AJAX: delete all Google listings for a single location and return updated cell HTML.
     */
    public static function ajax_google_delete_location(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('edp_google_location_actions', 'nonce');

        $id = max(0, absint( wp_unslash( $_POST['location_id'] ?? 0 ) ) );

        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid location ID']);
        }

        EDP_Database::delete_nearby_for_location($id, 'google');

        wp_send_json_success([
            'html'  => self::build_listing_cell_html($id, 0),
            'count' => 0,
        ]);
    }

    /**
     * AJAX: create a CPT static page for a location (Create button in Static Page column).
     * Returns updated cell HTML so the caller can swap it without a full page reload.
     */
    public static function ajax_create_location_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        check_ajax_referer('edp_create_location_page', 'nonce');

        $location_id = max(0, absint(wp_unslash($_POST['location_id'] ?? 0)));

        if ($location_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid location.', 'emergencydentalpros')]);
        }

        $row = EDP_Database::get_row_by_id($location_id);

        if ($row === null) {
            wp_send_json_error(['message' => esc_html__('Location not found.', 'emergencydentalpros')]);
        }

        if ((string) ($row['override_type'] ?? '') === 'cpt' && (int) ($row['custom_post_id'] ?? 0) > 0) {
            wp_send_json_error(['message' => esc_html__('Static page already exists.', 'emergencydentalpros')]);
        }

        $settings = EDP_Settings::get_all();
        $tpl      = $settings['templates']['city_landing'] ?? [];
        $base     = EDP_Template_Engine::base_vars();
        $vars     = EDP_Template_Engine::context_from_city_row($base, $row);
        $title    = EDP_Template_Engine::replace((string) ($tpl['meta_title'] ?? ''), $vars);
        $body     = EDP_Template_Engine::replace((string) ($tpl['body'] ?? ''), $vars);

        $post_id = wp_insert_post(
            [
                'post_type'    => EDP_CPT::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $body,
            ],
            true
        );

        if (is_wp_error($post_id) || $post_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Failed to create page.', 'emergencydentalpros')]);
        }

        update_post_meta((int) $post_id, '_edp_location_id', $location_id);

        global $wpdb;
        $table = EDP_Database::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            $table,
            ['custom_post_id' => (int) $post_id, 'override_type' => 'cpt'],
            ['id' => $location_id],
            ['%d', '%s'],
            ['%d']
        );

        $edit_url = get_edit_post_link((int) $post_id);
        $link     = $edit_url
            ? '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer" class="edp-page-link">#' . (int) $post_id . '</a>'
            : '<span class="edp-page-link">#' . (int) $post_id . '</span>';

        $cell_html = '<span class="edp-static-page-cell">'
            . $link
            . '<button type="button" '
            . 'class="edp-listing-btn edp-listing-btn--danger edp-clear-cpt-btn" '
            . 'data-location-id="' . esc_attr((string) $location_id) . '" '
            . 'title="' . esc_attr__('Remove static page override', 'emergencydentalpros') . '">'
            . '<span class="dashicons dashicons-trash" aria-hidden="true"></span>'
            . '</button>'
            . '</span>';

        wp_send_json_success(['html' => $cell_html, 'post_id' => (int) $post_id]);
    }

    /**
     * AJAX: save a post mapping for a location (Map Post column).
     */
    public static function ajax_save_post_mapping(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        check_ajax_referer('edp_map_post', 'nonce');

        $location_id = max(0, absint(wp_unslash($_POST['location_id'] ?? 0)));
        $post_id     = max(0, absint(wp_unslash($_POST['post_id'] ?? 0)));

        if ($location_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid location.', 'emergencydentalpros')]);
        }

        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error(['not_found' => true, 'message' => esc_html__('Post not found.', 'emergencydentalpros')]);
        }

        global $wpdb;
        $table = EDP_Database::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            $table,
            ['custom_post_id' => $post_id, 'override_type' => 'mapped'],
            ['id' => $location_id],
            ['%d', '%s'],
            ['%d']
        );

        wp_send_json_success(['post_id' => $post_id]);
    }

    /**
     * AJAX: clear the static page override for a location.
     * Pass delete_post=1 to also permanently delete the linked WordPress post.
     */
    public static function ajax_clear_override(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        check_ajax_referer('edp_clear_override', 'nonce');

        $location_id = max(0, absint(wp_unslash($_POST['location_id'] ?? 0)));
        $delete_post = !empty($_POST['delete_post']);

        if ($location_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid location.', 'emergencydentalpros')]);
        }

        if ($delete_post) {
            $row = EDP_Database::get_row_by_id($location_id);
            if ($row !== null) {
                $post_id = (int) ($row['custom_post_id'] ?? 0);
                if ($post_id > 0) {
                    wp_delete_post($post_id, true);
                }
            }
        }

        global $wpdb;
        $table = EDP_Database::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET custom_post_id = NULL, override_type = NULL WHERE id = %d",
                $location_id
            )
        );

        wp_send_json_success(['cleared' => true]);
    }

    /**
     * AJAX: permanently delete a location row plus its Google nearby data and linked CPT post.
     */
    public static function ajax_delete_location_row(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Forbidden', 'emergencydentalpros')], 403);
        }

        check_ajax_referer('edp_delete_location_row', 'nonce');

        $location_id = max(0, absint(wp_unslash($_POST['location_id'] ?? 0)));

        if ($location_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid location.', 'emergencydentalpros')]);
        }

        $row = EDP_Database::get_row_by_id($location_id);
        if ($row !== null) {
            $post_id = (int) ($row['custom_post_id'] ?? 0);
            if ($post_id > 0 && (string) ($row['override_type'] ?? '') === 'cpt') {
                wp_delete_post($post_id, true);
            }
        }

        EDP_Database::delete_nearby_for_location($location_id);

        global $wpdb;
        $table = EDP_Database::table_name();
        $wpdb->delete($table, ['id' => $location_id], ['%d']);

        wp_send_json_success(['deleted' => true, 'location_id' => $location_id]);
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

        $offset        = max(0, absint( wp_unslash( $_POST['offset'] ?? 0 ) ) );
        $step          = max(0, absint( wp_unslash( $_POST['step'] ?? 0 ) ) );
        $total         = max(1, min(300, absint( wp_unslash( $_POST['total'] ?? 1 ) ) ) );
        $fetch_details = ! empty( $_POST['fetch_details'] );

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

    /**
     * Dev-only AJAX: import locations from the bundled raw_data.csv.
     * Only registered when WP_DEBUG is true. Used by Playwright test seed step.
     */
    public static function ajax_dev_seed_csv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('edp_dev_seed_csv', 'nonce');

        $path = EDP_PLUGIN_DIR . 'raw_data.csv';

        if (!is_readable($path)) {
            wp_send_json_error(['message' => 'raw_data.csv not readable at: ' . $path]);
        }

        $result = EDP_Importer::import_from_csv_file($path);

        if (!empty($result['error'])) {
            wp_send_json_error($result);
        }

        wp_send_json_success($result);
    }

    // ── PageSpeed ────────────────────────────────────────────────────────────

    /**
     * Build the HTML for a single row's SEO status cell.
     * $cache is null (no data yet) or a decoded row from EDP_Database::get_pagespeed_cache().
     *
     * @param array<string, mixed>|null $cache
     */
    public static function build_seo_cell_html(int $location_id, ?array $cache): string
    {
        $nonce = wp_create_nonce('edp_check_pagespeed');

        if ($cache === null || empty($cache['mobile_score'])) {
            return sprintf(
                '<button type="button" class="edp-listing-btn edp-check-seo-btn" '
                . 'data-location-id="%1$d" data-nonce="%2$s" '
                . 'title="%3$s">'
                . '<span class="dashicons dashicons-performance" aria-hidden="true"></span> %4$s'
                . '</button>',
                $location_id,
                esc_attr($nonce),
                esc_attr__('Run PageSpeed Insights check', 'emergencydentalpros'),
                esc_html__('Check SEO', 'emergencydentalpros')
            );
        }

        $mobile_score  = (int) $cache['mobile_score'];
        $desktop_score = (int) $cache['desktop_score'];
        $status        = EDP_Pagespeed_Client::status($mobile_score);
        $checked_at    = isset($cache['checked_at'])
            ? sprintf(
                /* translators: %s time ago string */
                __('%s ago', 'emergencydentalpros'),
                human_time_diff(strtotime((string) $cache['checked_at']), current_time('timestamp'))
            )
            : '';

        $mobile_metrics  = is_array($cache['mobile_metrics'])  ? $cache['mobile_metrics']  : [];
        $desktop_metrics = is_array($cache['desktop_metrics']) ? $cache['desktop_metrics'] : [];

        return sprintf(
            '<div class="edp-seo-cell">'
            . '<div class="edp-seo-indicator edp-seo--%1$s" '
            .     'data-location-id="%2$d" '
            .     'data-mobile-score="%3$d" '
            .     'data-desktop-score="%4$d" '
            .     'data-mobile-metrics="%5$s" '
            .     'data-desktop-metrics="%6$s" '
            .     'data-checked-at="%7$s">'
            .     '<span class="edp-seo-dot" aria-hidden="true"></span>'
            .     '<span class="edp-seo-score">%3$d</span>'
            . '</div>'
            . '<button type="button" class="edp-recheck-seo-btn" '
            .     'data-location-id="%2$d" data-nonce="%8$s" '
            .     'title="%9$s">'
            .     '<span class="dashicons dashicons-update" aria-hidden="true"></span>'
            . '</button>'
            . '</div>',
            esc_attr($status),
            $location_id,
            $mobile_score,
            $desktop_score,
            esc_attr((string) wp_json_encode($mobile_metrics)),
            esc_attr((string) wp_json_encode($desktop_metrics)),
            esc_attr($checked_at),
            esc_attr($nonce),
            esc_attr__('Recheck PageSpeed', 'emergencydentalpros')
        );
    }

    /**
     * AJAX: run PageSpeed Insights (mobile + desktop) for one location and store the result.
     */
    public static function ajax_check_pagespeed(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('edp_check_pagespeed', 'nonce');

        $id = max(0, absint(wp_unslash($_POST['location_id'] ?? 0)));

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid location ID.', 'emergencydentalpros')]);
        }

        $api_key = EDP_Google_Places_Config::get_api_key();

        if ($api_key === '') {
            wp_send_json_error(['message' => __('Google API key not configured. Set it on the Import settings page.', 'emergencydentalpros')]);
        }

        $row = EDP_Database::get_row_by_id($id);

        if (!is_array($row)) {
            wp_send_json_error(['message' => __('Location not found.', 'emergencydentalpros')]);
        }

        $url    = home_url(user_trailingslashit('locations/' . rawurlencode((string) $row['state_slug']) . '/' . rawurlencode((string) $row['city_slug'])));
        $client = new EDP_Pagespeed_Client($api_key);

        // Allow long execution — each PSI call takes 5–15 s.
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        @set_time_limit(180);

        $mobile = $client->check($url, 'mobile');

        if (is_wp_error($mobile)) {
            wp_send_json_error([
                'message' => $mobile->get_error_message(),
                'debug'   => [
                    'url'     => $url,
                    'key_prefix' => substr($api_key, 0, 8) . '...',
                    'strategy' => 'mobile',
                ],
            ]);
        }

        $desktop = $client->check($url, 'desktop');

        if (is_wp_error($desktop)) {
            wp_send_json_error([
                'message' => $desktop->get_error_message(),
                'debug'   => [
                    'url'     => $url,
                    'key_prefix' => substr($api_key, 0, 8) . '...',
                    'strategy' => 'desktop',
                ],
            ]);
        }

        EDP_Database::upsert_pagespeed_cache($id, $mobile, $desktop);

        $cache = EDP_Database::get_pagespeed_cache($id);

        wp_send_json_success([
            'html'           => self::build_seo_cell_html($id, $cache),
            'mobile_score'   => $mobile['score'],
            'desktop_score'  => $desktop['score'],
        ]);
    }
}
