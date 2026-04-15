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

    /** @var array<int, bool> location id => has Google Places rows */
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
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'emergencydentalpros'),
            'state' => __('State', 'emergencydentalpros'),
            'city' => __('City', 'emergencydentalpros'),
            'google' => __('Google', 'emergencydentalpros'),
            'zips' => __('ZIPs', 'emergencydentalpros'),
            'override' => __('Override', 'emergencydentalpros'),
            'edp_actions' => __('Actions', 'emergencydentalpros'),
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
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
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

        $has = !empty($this->nearby_status_map[$id]);

        if ($has) {
            return '<span class="edp-google-status edp-google-status--ok" title="' . esc_attr__('Google data saved', 'emergencydentalpros') . '" aria-label="' . esc_attr__('Google data saved', 'emergencydentalpros') . '">'
                . '<span class="edp-google-dot"></span></span>';
        }

        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="edp-google-fetch-one">
            <?php wp_nonce_field('edp_seo_google_single', 'edp_seo_google_single_nonce'); ?>
            <input type="hidden" name="action" value="edp_seo_google_fetch_single" />
            <input type="hidden" name="location_id" value="<?php echo esc_attr((string) $id); ?>" />
            <button type="submit" class="button button-small"><?php esc_html_e('Fetch Google', 'emergencydentalpros'); ?></button>
        </form>
        <?php

        return (string) ob_get_clean();
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
        $city_slug = sanitize_title((string) ($item['city_slug'] ?? ''));
        $url = home_url(user_trailingslashit('locations/' . rawurlencode($state_slug) . '/' . rawurlencode($city_slug)));

        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) ($item['city_name'] ?? '')) . '</a>';
    }

    public function column_zips($item): string
    {
        $zips = [];

        if (!empty($item['zips'])) {
            $decoded = json_decode((string) $item['zips'], true);

            if (is_array($decoded)) {
                $zips = $decoded;
            }
        }

        $count = count($zips);

        return esc_html((string) $count);
    }

    public function column_override($item): string
    {
        $type = isset($item['override_type']) ? (string) $item['override_type'] : '';
        $pid = isset($item['custom_post_id']) ? (int) $item['custom_post_id'] : 0;

        if ($pid > 0 && $type !== '') {
            $link = get_edit_post_link($pid);

            if ($link) {
                return esc_html($type) . ' (<a href="' . esc_url($link) . '">#' . esc_html((string) $pid) . '</a>)';
            }

            return esc_html($type) . ' (#' . esc_html((string) $pid) . ')';
        }

        return '—';
    }

    public function column_edp_actions($item): string
    {
        $id = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        ob_start();

        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('edp_seo_location_action', 'edp_seo_location_nonce'); ?>
            <input type="hidden" name="action" value="edp_seo_location_action" />
            <input type="hidden" name="edp_action" value="map_post" />
            <input type="hidden" name="location_id" value="<?php echo esc_attr((string) $id); ?>" />
            <input type="number" name="post_id" placeholder="<?php esc_attr_e('Post ID', 'emergencydentalpros'); ?>" min="1" style="width:90px;" />
            <button type="submit" class="button button-small"><?php esc_html_e('Map', 'emergencydentalpros'); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('edp_seo_location_action', 'edp_seo_location_nonce'); ?>
            <input type="hidden" name="action" value="edp_seo_location_action" />
            <input type="hidden" name="edp_action" value="create_cpt" />
            <input type="hidden" name="location_id" value="<?php echo esc_attr((string) $id); ?>" />
            <button type="submit" class="button button-small"><?php esc_html_e('Create CPT', 'emergencydentalpros'); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
            <?php wp_nonce_field('edp_seo_location_action', 'edp_seo_location_nonce'); ?>
            <input type="hidden" name="action" value="edp_seo_location_action" />
            <input type="hidden" name="edp_action" value="clear_override" />
            <input type="hidden" name="location_id" value="<?php echo esc_attr((string) $id); ?>" />
            <button type="submit" class="button button-small"><?php esc_html_e('Clear', 'emergencydentalpros'); ?></button>
        </form>
        <?php

        return (string) ob_get_clean();
    }
}
