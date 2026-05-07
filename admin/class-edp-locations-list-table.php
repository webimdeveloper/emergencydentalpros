<?php
/**
 * Locations admin list table.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class EDP_Locations_List_Table extends WP_List_Table
{
    /** @var string Last SELECT SQL (for admin diagnostics). */
    public $debug_last_sql = '';

    /** @var string $wpdb->last_error after last query. */
    public $debug_last_db_error = '';

    /** @var int Rows returned for current page. */
    public $debug_rows_returned = 0;

    /** @var int Total rows in table (COUNT). */
    public $debug_total_count = 0;

    /** @var array<int, int> location id => count of Google Places rows (0 = none) */
    public $nearby_status_map = [];

    /** @var array<int, array<string, mixed>> location id => pagespeed cache row */
    public $pagespeed_map = [];

    /** @var array<int, array<string, mixed>> location id => CQS cache row */
    public $cqs_map = [];

    /** @var array<string, object> city_slug => WP_Post for conflict detection (flat mode only) */
    public $conflict_map = [];

    /**
     * @param string $screen_hook Return value of add_submenu_page for this screen (required so
     *                            get_column_headers() and manage_{$screen->id}_columns work).
     */
    public function __construct(string $screen_hook = '')
    {
        $args = [
            'singular' => 'location',
            'plural' => 'locations',
            'ajax' => false,
        ];

        if ($screen_hook !== '') {
            $args['screen'] = $screen_hook;
        }

        parent::__construct($args);
    }

    public function get_sortable_columns(): array
    {
        return [
            'google'   => ['google', false],
            'override' => ['override', false],
        ];
    }

    public function get_columns(): array
    {
        $cols = [
            'cb'          => '<input type="checkbox" />',
            'id'          => __('ID', 'emergencydentalpros'),
            'state'       => __('State', 'emergencydentalpros'),
            'city'        => __('City', 'emergencydentalpros'),
            'google'      => __('Google Business', 'emergencydentalpros') . '<span class="dashicons dashicons-arrow-right-alt2 edp-col-arrow" aria-hidden="true"></span>',
            'override'    => __('Static Page', 'emergencydentalpros') . '<span class="dashicons dashicons-arrow-right-alt2 edp-col-arrow" aria-hidden="true"></span>',
            // MVP: columns hidden until features are ready for client
            // 'seo'         => __('SEO', 'emergencydentalpros'),
            // 'cqs'         => __('CQS', 'emergencydentalpros'),
            'edp_actions' => __('Map Post', 'emergencydentalpros'),
        ];

        if (EDP_Rewrite::get_url_mode() === 'flat') {
            $cols['url_conflict'] = __('URL', 'emergencydentalpros');
        }

        return $cols;
    }

    public function no_items(): void
    {
        esc_html_e('No rows in the locations table yet. Use the Import screen (see notice above) and ensure raw_data.csv is readable on this server.', 'emergencydentalpros');
    }

    /**
     * Do not set `_column_headers` manually with only 3 elements — WP_List_Table expects
     * [columns, hidden, sortable, primary] or `get_column_info()` breaks (colspan, stray header text).
     */
    protected function get_primary_column_name(): string
    {
        return 'city';
    }

    /**
     * Build column headers from this class instead of get_column_headers( $screen ).
     *
     * On some plugin menus the screen id does not match the manage_{$screen->id}_columns
     * filter WordPress registers, so the parent returns empty columns and the table body
     * renders with no cells (even when $this->items is populated).
     */
    protected function get_column_info(): array
    {
        $columns = $this->get_columns();
        $hidden = [];

        if (isset($this->screen) && $this->screen instanceof WP_Screen) {
            $hidden = get_hidden_columns($this->screen);
        }

        $sortable = $this->get_sortable_columns();
        $primary = $this->get_primary_column_name();

        $this->_column_headers = [$columns, $hidden, $sortable, $primary];

        return $this->_column_headers;
    }

    public function get_bulk_actions(): array
    {
        return [
            'fetch_google'    => __('Fetch Google', 'emergencydentalpros'),
            'create_pages'    => __('Create Pages', 'emergencydentalpros'),
            'analyze_content' => __('Analyze Content', 'emergencydentalpros'),
            'delete_rows'     => __('Delete Rows', 'emergencydentalpros'),
        ];
    }

    /**
     * GET bulk actions must keep ?page= so WordPress loads this screen (hidden field in list form).
     *
     * @param string $which top|bottom
     */
    protected function extra_tablenav($which): void
    {
        if ($which === 'top') {
            echo '<input type="hidden" name="page" value="edp-seo-locations" />';
        }
    }

    public function prepare_items(): void
    {
        global $wpdb;

        $table      = EDP_Database::table_name();
        $near_table = EDP_Database::nearby_table_name();
        $per_page   = 20;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP_List_Table pagination param.
        $paged  = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $offset = ($paged - 1) * $per_page;

        // Filters (no nonce needed — read-only display params).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state_filter = isset($_GET['state_filter']) ? sanitize_text_field(wp_unslash($_GET['state_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $city_filter = isset($_GET['city_filter']) ? sanitize_text_field(wp_unslash($_GET['city_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $has_faq_filter    = isset($_GET['has_faq'])    && $_GET['has_faq']    === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $has_static_filter = isset($_GET['has_static']) && $_GET['has_static'] === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $has_mapped_filter = isset($_GET['has_mapped']) && $_GET['has_mapped'] === '1';

        // Sort (WP_List_Table standard params).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = (isset($_GET['order']) && strtolower((string) wp_unslash($_GET['order'])) === 'asc') ? 'ASC' : 'DESC';

        // Build WHERE clause.
        $where_parts  = ['1=1'];
        $where_values = [];

        if ($state_filter !== '') {
            $where_parts[]  = 'l.state_slug = %s';
            $where_values[] = $state_filter;
        }

        if ($city_filter !== '') {
            $where_parts[]  = 'l.city_name LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($city_filter) . '%';
        }

        if ($has_static_filter) {
            $where_parts[] = "l.override_type = 'cpt'";
            $where_parts[] = 'l.custom_post_id > 0';
        }

        if ($has_mapped_filter) {
            $where_parts[] = "l.override_type = 'mapped'";
            $where_parts[] = 'l.custom_post_id > 0';
        }

        if ($has_faq_filter) {
            $where_parts[] = 'l.custom_post_id > 0';
            $where_parts[] = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = l.custom_post_id AND pm.meta_key = '_edp_faq_enabled' AND pm.meta_value = '1')";
        }

        $where = implode(' AND ', $where_parts);

        // ORDER BY clause.
        if ($orderby === 'google') {
            $order_sql = "COALESCE(nb.gcount, 0) {$order}, l.state_name ASC, l.city_name ASC";
        } elseif ($orderby === 'override') {
            $order_sql = "(CASE WHEN l.custom_post_id > 0 AND l.override_type IN ('cpt','mapped') THEN 1 ELSE 0 END) {$order}, l.state_name ASC, l.city_name ASC";
        } else {
            $order_sql = 'l.state_name ASC, l.city_name ASC';
        }

        // Total count (respects filters).
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} l WHERE {$where}", ...$where_values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        $this->debug_total_count  = $total;
        $this->debug_last_db_error = (string) $wpdb->last_error;

        $per_page = max(1, min(100, (int) $per_page));
        $offset   = max(0, (int) $offset);

        // Main SELECT — LEFT JOIN brings Google count for sort; LIMIT/OFFSET are integers.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $base_sql = "SELECT l.id, l.state_id, l.state_name, l.state_slug, l.city_name, l.city_slug, l.zips, l.custom_post_id, l.override_type,
            COALESCE(nb.gcount, 0) AS google_count
            FROM {$table} l
            LEFT JOIN (SELECT location_id, COUNT(*) AS gcount FROM {$near_table} GROUP BY location_id) nb ON l.id = nb.location_id
            WHERE {$where}
            ORDER BY {$order_sql}
            LIMIT {$per_page} OFFSET {$offset}";

        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($base_sql, ...$where_values);
        } else {
            $sql = $base_sql;
        }

        $this->debug_last_sql = $sql;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ((string) $wpdb->last_error !== '') {
            $this->debug_last_db_error = (string) $wpdb->last_error;
        }

        $this->items              = is_array($rows) ? $rows : [];
        $this->debug_rows_returned = count($this->items);

        $ids = [];

        foreach ($this->items as $it) {
            if (is_array($it) && isset($it['id'])) {
                $ids[] = (int) $it['id'];
            }
        }

        $this->nearby_status_map = EDP_Database::get_nearby_status_for_locations($ids);
        $this->pagespeed_map     = EDP_Database::get_pagespeed_for_locations($ids);
        $this->cqs_map           = EDP_Database::get_cqs_for_locations($ids);

        if (EDP_Rewrite::get_url_mode() === 'flat') {
            $city_slugs = [];
            foreach ($this->items as $it) {
                if (is_array($it) && !empty($it['city_slug'])) {
                    $city_slugs[] = sanitize_title((string) $it['city_slug']);
                }
            }
            $this->conflict_map = EDP_Database::find_wp_slug_conflicts_bulk($city_slugs);
        }

        $this->set_pagination_args(
            [
                'total_items' => $total,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total / $per_page),
            ]
        );
    }

    /**
     * Column headers as resolved by WP (screen + filters). For diagnostics only.
     *
     * @return array{0: array<string, string>, 1: array<int, string>, 2: array<string, mixed>, 3: string}|array<int, mixed>
     */
    public function get_resolved_column_info(): array
    {
        return $this->get_column_info();
    }

    /**
     * @return array<string, string>
     */
    public function get_debug_screen_info(): array
    {
        $out = [
            'screen_id' => '',
            'screen_base' => '',
            'parent_file' => '',
            'list_table_screen_set' => isset($this->screen) && $this->screen ? 'yes' : 'no',
        ];

        if (isset($this->screen) && $this->screen instanceof WP_Screen) {
            $out['screen_id'] = (string) $this->screen->id;
            $out['screen_base'] = (string) $this->screen->base;
            $out['parent_file'] = (string) $this->screen->parent_file;
        }

        return $out;
    }

    public function column_default($item, $column_name)
    {
        return '';
    }

    public function column_cb($item): string
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;

        if ($id <= 0) {
            return '';
        }

        return sprintf(
            '<input type="checkbox" name="location[]" id="cb-select-%1$d" value="%1$d" />',
            $id
        );
    }

    public function column_id($item): string
    {
        return esc_html((string) ($item['id'] ?? ''));
    }

    public function column_google($item): string
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;

        if ($id <= 0) {
            return '';
        }

        $count = (int) ($this->nearby_status_map[$id] ?? 0);

        return EDP_Admin::build_listing_cell_html($id, $count);
    }

    public function column_state($item): string
    {
        $name = isset($item['state_name']) ? (string) $item['state_name'] : '';
        $abbr = isset($item['state_id']) ? strtoupper((string) $item['state_id']) : '';

        return esc_html($name . ' (' . $abbr . ')');
    }

    public function column_city($item): string
    {
        $url = EDP_Rewrite::city_url($item);
        $id         = (int) ($item['id'] ?? 0);

        $city_link = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
            . '<span class="dashicons dashicons-admin-site-alt3 edp-city-globe" aria-hidden="true"></span>'
            . esc_html((string) ($item['city_name'] ?? ''))
            . '</a>';

        $delete_btn = '<button type="button" class="edp-row-delete-btn button-link-delete" '
            . 'data-location-id="' . esc_attr((string) $id) . '" '
            . 'data-nonce="' . esc_attr(wp_create_nonce('edp_delete_location_row')) . '">'
            . esc_html__('Delete row', 'emergencydentalpros')
            . '</button>';

        return $city_link . $this->row_actions(['delete' => $delete_btn]);
    }

    public function column_url_conflict($item): string
    {
        $slug     = sanitize_title((string) ($item['city_slug'] ?? ''));
        $loc_id   = (int) ($item['id'] ?? 0);
        $ignored  = get_option('edp_ignored_conflicts', []);
        $is_ignored = is_array($ignored) && in_array($slug, $ignored, true);

        if (!isset($this->conflict_map[$slug])) {
            return '<span class="edp-conflict-ok dashicons dashicons-yes-alt" title="' . esc_attr__('No conflict', 'emergencydentalpros') . '"></span>';
        }

        $conflict_post = $this->conflict_map[$slug];
        $post_id    = (int) $conflict_post->ID;
        $post_title = (string) $conflict_post->post_title;
        $edit_url   = get_edit_post_link($post_id);

        if ($is_ignored) {
            return '<span class="edp-conflict-ignored" title="' . esc_attr__('Conflict ignored', 'emergencydentalpros') . '">&#9888; '
                . esc_html__('Ignored', 'emergencydentalpros')
                . '</span>';
        }

        $migrate_btn = '<button type="button" class="edp-migrate-btn button button-small" '
            . 'data-location-id="' . esc_attr((string) $loc_id) . '" '
            . 'data-conflict-post-id="' . esc_attr((string) $post_id) . '" '
            . 'data-conflict-post-title="' . esc_attr($post_title) . '" '
            . 'data-city-slug="' . esc_attr($slug) . '" '
            . 'data-nonce="' . esc_attr(wp_create_nonce('edp_migrate_location')) . '">'
            . esc_html__('Migrate & Take Over', 'emergencydentalpros')
            . '</button>';

        $ignore_btn = '<button type="button" class="edp-ignore-conflict-btn button-link" '
            . 'data-city-slug="' . esc_attr($slug) . '" '
            . 'data-nonce="' . esc_attr(wp_create_nonce('edp_ignore_conflict_' . $slug)) . '">'
            . esc_html__('Ignore', 'emergencydentalpros')
            . '</button>';

        $edit_link = $edit_url
            ? '<a href="' . esc_url($edit_url) . '">' . esc_html($post_title) . '</a>'
            : esc_html($post_title);

        return '<span class="edp-conflict-badge">&#9888; ' . esc_html__('Conflict', 'emergencydentalpros') . '</span>'
            . '<div class="edp-conflict-detail">' . $edit_link . '</div>'
            . '<div class="edp-conflict-actions">' . $migrate_btn . ' ' . $ignore_btn . '</div>';
    }

    /**
     * Static Page column: "Create" button when no CPT exists; link + trash when CPT is set.
     */
    public function column_override($item): string
    {
        $type = isset($item['override_type']) ? (string) $item['override_type'] : '';
        $pid  = isset($item['custom_post_id']) ? (int) $item['custom_post_id'] : 0;
        $id   = (int) ($item['id'] ?? 0);

        if ($pid > 0 && $type === 'cpt') {
            $post_status = get_post_status($pid);
            $clear_btn   = '<button type="button" '
                . 'class="edp-listing-btn edp-listing-btn--danger edp-clear-cpt-btn" '
                . 'data-location-id="' . esc_attr((string) $id) . '" '
                . 'title="' . esc_attr__('Remove static page override', 'emergencydentalpros') . '">'
                . '<span class="dashicons dashicons-trash" aria-hidden="true"></span>'
                . '</button>';

            if ($post_status === false) {
                $link = '<span class="edp-page-link edp-page-link--dead">'
                    . esc_html__('Missing', 'emergencydentalpros') . ' #' . esc_html((string) $pid)
                    . '</span>';
            } elseif ($post_status === 'trash') {
                $link = '<span class="edp-page-link edp-page-link--dead">'
                    . esc_html__('Trashed', 'emergencydentalpros') . ' #' . esc_html((string) $pid)
                    . '</span>';
            } else {
                $edit_url = get_edit_post_link($pid);
                $link     = $edit_url
                    ? '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer" class="edp-page-link">#' . esc_html((string) $pid) . '</a>'
                    : '<span class="edp-page-link">#' . esc_html((string) $pid) . '</span>';
            }

            return '<span class="edp-static-page-cell">' . $link . $clear_btn . '</span>';
        }

        if ($id <= 0) {
            return '—';
        }

        return '<button type="button" class="edp-listing-btn edp-btn-create edp-create-page-btn" '
            . 'data-location-id="' . esc_attr((string) $id) . '" '
            . 'title="' . esc_attr__('Create static page from template', 'emergencydentalpros') . '">'
            . '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>'
            . esc_html__('Create', 'emergencydentalpros')
            . '</button>';
    }

    /**
     * SEO column: "Check SEO" button or status indicator with recheck button.
     */
    public function column_seo($item): string
    {
        $id = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $cache = isset($this->pagespeed_map[$id]) ? $this->pagespeed_map[$id] : null;

        return EDP_Admin::build_seo_cell_html($id, $cache);
    }

    /**
     * CQS column: "Analyze" button or score indicator with re-analyze button.
     */
    public function column_cqs($item): string
    {
        $id = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $cache = isset($this->cqs_map[$id]) ? $this->cqs_map[$id] : null;

        return EDP_Admin::build_cqs_cell_html($id, $cache);
    }

    /**
     * Map Post column: post ID input field; AJAX save on Enter/blur.
     *
     * For redirect-only rows (override_type='mapped') the value comes from
     * custom_post_id. For CPT rows the redirect post ID lives in CPT meta so
     * the static page and its redirect source can coexist.
     */
    public function column_edp_actions($item): string
    {
        $id   = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $pid  = isset($item['custom_post_id']) ? (int) $item['custom_post_id'] : 0;
        $type = isset($item['override_type']) ? (string) $item['override_type'] : '';

        if ($type === 'mapped' && $pid > 0) {
            $val     = $pid;
            $has_cpt = '0';
        } elseif ($type === 'cpt' && $pid > 0) {
            $redirect_pid = (int) get_post_meta($pid, '_edp_redirect_post_id', true);
            $val          = $redirect_pid > 0 ? $redirect_pid : '';
            $has_cpt      = '1';
        } else {
            $val     = '';
            $has_cpt = '0';
        }

        return '<div class="edp-map-post-wrap">'
            . sprintf(
                '<input type="number" class="edp-map-post-input" '
                . 'data-location-id="%1$d" '
                . 'data-has-cpt="%4$s" '
                . 'value="%2$s" '
                . 'placeholder="%3$s" '
                . 'min="1" />',
                $id,
                esc_attr((string) $val),
                esc_attr__('Post ID', 'emergencydentalpros'),
                esc_attr($has_cpt)
            )
            . '<button type="button" class="edp-map-clear-btn" '
            . 'data-location-id="' . esc_attr((string) $id) . '" '
            . 'data-has-cpt="' . esc_attr($has_cpt) . '" '
            . 'title="' . esc_attr__('Clear mapping', 'emergencydentalpros') . '"'
            . ($val === '' ? ' style="display:none;"' : '')
            . '>&times;</button>'
            . '</div>';
    }
}
