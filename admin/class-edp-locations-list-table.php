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
        return [];
    }

    public function get_columns(): array
    {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __('ID', 'emergencydentalpros'),
            'state'       => __('State', 'emergencydentalpros'),
            'city'        => __('City', 'emergencydentalpros'),
            'google'      => __('Google Business', 'emergencydentalpros'),
            'override'    => __('Static Page', 'emergencydentalpros'),
            'edp_actions' => __('Map Post', 'emergencydentalpros'),
        ];
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
            'fetch_google' => __('Fetch Google', 'emergencydentalpros'),
            'create_pages' => __('Create Pages', 'emergencydentalpros'),
            'delete_rows'  => __('Delete Rows', 'emergencydentalpros'),
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

        $table = EDP_Database::table_name();
        $per_page = 20;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP_List_Table pagination param.
        $paged = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
        $offset = ($paged - 1) * $per_page;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $this->debug_total_count = $total;
        $this->debug_last_db_error = (string) $wpdb->last_error;

        // Use integer LIMIT/OFFSET (some drivers mis-handle placeholders here).
        $per_page = max(1, min(100, (int) $per_page));
        $offset = max(0, (int) $offset);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal; LIMIT/OFFSET are absint.
        $sql = "SELECT id, state_id, state_name, state_slug, city_name, city_slug, zips, custom_post_id, override_type
            FROM {$table}
            ORDER BY state_name ASC, city_name ASC
            LIMIT {$per_page} OFFSET {$offset}";

        $this->debug_last_sql = $sql;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ((string) $wpdb->last_error !== '') {
            $this->debug_last_db_error = (string) $wpdb->last_error;
        }

        $this->items = is_array($rows) ? $rows : [];
        $this->debug_rows_returned = count($this->items);

        $ids = [];

        foreach ($this->items as $it) {
            if (is_array($it) && isset($it['id'])) {
                $ids[] = (int) $it['id'];
            }
        }

        $this->nearby_status_map = EDP_Database::get_nearby_status_for_locations($ids);

        $this->set_pagination_args(
            [
                'total_items' => $total,
                'per_page' => $per_page,
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
        $state_slug = sanitize_title((string) ($item['state_slug'] ?? ''));
        $city_slug  = sanitize_title((string) ($item['city_slug'] ?? ''));
        $url        = home_url(user_trailingslashit('locations/' . rawurlencode($state_slug) . '/' . rawurlencode($city_slug)));
        $id         = (int) ($item['id'] ?? 0);

        $city_link = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html((string) ($item['city_name'] ?? '')) . '</a>';

        $actions = [];
        if ($id > 0) {
            $actions['delete'] = '<a href="#" class="edp-row-delete-btn" '
                . 'data-location-id="' . esc_attr((string) $id) . '" '
                . 'style="color:#b32d2e;">'
                . esc_html__('Delete', 'emergencydentalpros')
                . '</a>';
        }

        return $city_link . $this->row_actions($actions);
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
     * Map Post column: post ID input field; AJAX save on Enter/blur.
     */
    public function column_edp_actions($item): string
    {
        $id   = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $pid  = isset($item['custom_post_id']) ? (int) $item['custom_post_id'] : 0;
        $type = isset($item['override_type']) ? (string) $item['override_type'] : '';
        $val  = ($type === 'mapped' && $pid > 0) ? $pid : '';

        return '<div class="edp-map-post-wrap">'
            . sprintf(
                '<input type="number" class="edp-map-post-input" '
                . 'data-location-id="%1$d" '
                . 'value="%2$s" '
                . 'placeholder="%3$s" '
                . 'min="1" />',
                $id,
                esc_attr((string) $val),
                esc_attr__('Post ID', 'emergencydentalpros')
            )
            . '<button type="button" class="edp-map-clear-btn" '
            . 'data-location-id="' . esc_attr((string) $id) . '" '
            . 'title="' . esc_attr__('Clear mapping', 'emergencydentalpros') . '"'
            . ($val === '' ? ' style="display:none;"' : '')
            . '>&times;</button>'
            . '</div>';
    }
}
