<?php
/**
 * Location table helpers.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Database
{
    /**
     * Create or upgrade the locations table if missing or DB version changed.
     * Rsync/deploy does not run register_activation_hook — this keeps production in sync.
     */
    public static function ensure_schema(): void
    {
        global $wpdb;

        $table = self::table_name();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $version_ok = (string) get_option(EDP_Activator::OPTION_DB_VERSION, '') === EDP_Activator::DB_VERSION;

        if ($found !== $table || !$version_ok) {
            EDP_Activator::create_tables();
            update_option(EDP_Activator::OPTION_DB_VERSION, EDP_Activator::DB_VERSION, false);
        }
    }

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'seo_locations';
    }

    public static function nearby_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'seo_nearby_businesses';
    }

    /**
     * @param list<int> $ids
     * @return list<array{id:int, city_name:string, state_id:string}>
     */
    public static function get_locations_by_ids(array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $table = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        $sql = $wpdb->prepare(
            "SELECT id, city_name, state_id FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id ASC",
            ...$ids
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'city_name' => (string) ($r['city_name'] ?? ''),
                'state_id' => (string) ($r['state_id'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $ids Location row ids
     * @return array<int, int> id => count of nearby listings stored (any provider), 0 if none
     */
    public static function get_nearby_status_for_locations(array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $map = [];

        foreach ($ids as $id) {
            $map[$id] = 0;
        }

        if ($ids === []) {
            return $map;
        }

        $near = self::nearby_table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT location_id, COUNT(*) AS cnt FROM {$near} WHERE location_id IN ({$placeholders}) GROUP BY location_id",
            ...$ids
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['location_id'])) {
                    $map[(int) $row['location_id']] = (int) ($row['cnt'] ?? 0);
                }
            }
        }

        return $map;
    }

    /**
     * @return list<array{id:int, city_name:string, state_id:string}>
     */
    public static function get_locations_batch(int $offset, int $limit): array
    {
        global $wpdb;

        $table = self::table_name();
        $offset = max(0, $offset);
        $limit = max(1, min(500, $limit));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        $sql = $wpdb->prepare(
            "SELECT id, city_name, state_id FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'city_name' => (string) ($r['city_name'] ?? ''),
                'state_id' => (string) ($r['state_id'] ?? ''),
            ];
        }

        return $out;
    }

    public static function delete_nearby_for_location(int $location_id, string $provider = 'google'): void
    {
        global $wpdb;

        $table = self::nearby_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete(
            $table,
            [
                'location_id' => $location_id,
                'provider' => $provider,
            ],
            ['%d', '%s']
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function insert_nearby_row(array $row): void
    {
        global $wpdb;

        $table = self::nearby_table_name();

        $data = [
            'location_id' => (int) ($row['location_id'] ?? 0),
            'provider' => (string) ($row['provider'] ?? 'google'),
            'external_id' => (string) ($row['external_id'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'fetched_at' => isset($row['fetched_at']) ? (string) $row['fetched_at'] : current_time('mysql'),
        ];

        $format = ['%d', '%s', '%s', '%d', '%s', '%s'];

        if (array_key_exists('rating', $row) && is_numeric($row['rating'])) {
            $data['rating'] = round((float) $row['rating'], 2);
            $format[] = '%f';
        }

        if (isset($row['review_count']) && $row['review_count'] !== '') {
            $data['review_count'] = (int) $row['review_count'];
            $format[] = '%d';
        }

        if (!empty($row['phone'])) {
            $data['phone'] = (string) $row['phone'];
            $format[] = '%s';
        }

        if (!empty($row['image_url'])) {
            $data['image_url'] = (string) $row['image_url'];
            $format[] = '%s';
        }

        if (isset($row['hours_text']) && (string) $row['hours_text'] !== '') {
            $data['hours_text'] = (string) $row['hours_text'];
            $format[] = '%s';
        }

        if (!empty($row['business_url'])) {
            $data['business_url'] = (string) $row['business_url'];
            $format[] = '%s';
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert($table, $data, $format);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function get_nearby_for_location(int $location_id, string $provider = 'google'): array
    {
        global $wpdb;

        $table = self::nearby_table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE location_id = %d AND provider = %s ORDER BY sort_order ASC, id ASC",
            $location_id,
            $provider
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public static function count_rows(): int
    {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return (int) $count;
    }

    public static function count_static_pages(): int
    {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE override_type = 'cpt' AND custom_post_id > 0");
    }

    public static function count_mapped_posts(): int
    {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE override_type = 'mapped' AND custom_post_id > 0");
    }

    public static function count_with_custom_faq(): int
    {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT l.id) FROM {$table} l
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = l.custom_post_id
               AND pm.meta_key = '_edp_faq_enabled' AND pm.meta_value = '1'
             WHERE l.custom_post_id > 0"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function get_distinct_states(): array
    {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        $sql = "SELECT DISTINCT state_slug, state_name, state_id FROM {$table} ORDER BY state_name ASC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function get_cities_by_state_slug(string $state_slug): array
    {
        global $wpdb;

        $table = self::table_name();
        $state_slug = sanitize_title($state_slug);

        $sql = $wpdb->prepare(
            "SELECT id, city_slug, city_name, state_id, state_name, state_slug, zips
            FROM {$table}
            WHERE state_slug = %s
            ORDER BY city_name ASC",
            $state_slug
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public static function state_exists(string $state_slug): bool
    {
        global $wpdb;

        $table = self::table_name();
        $state_slug = sanitize_title($state_slug);

        $sql = $wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE state_slug = %s LIMIT 1",
            $state_slug
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $found = $wpdb->get_var($sql);

        return (string) $found === '1';
    }

    /**
     * All cities grouped by state_slug — one query, used by the state-list page accordion.
     *
     * @return array<string, list<array{city_name: string, city_slug: string}>>
     */
    public static function get_all_cities_grouped_by_state(): array
    {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT state_slug, city_name, city_slug FROM {$table} ORDER BY state_slug ASC, city_name ASC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $slug = (string) ($r['state_slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $grouped[$slug][] = [
                'city_name' => (string) ($r['city_name'] ?? ''),
                'city_slug' => (string) ($r['city_slug'] ?? ''),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_city_row(string $state_slug, string $city_slug): ?array
    {
        global $wpdb;

        $table = self::table_name();
        $state_slug = sanitize_title($state_slug);
        $city_slug = sanitize_title($city_slug);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE state_slug = %s AND city_slug = %s LIMIT 1",
            $state_slug,
            $city_slug
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Look up a city by city_slug alone (used in flat URL mode).
     *
     * @return array<string, mixed>|null
     */
    public static function get_city_row_by_slug(string $city_slug): ?array
    {
        global $wpdb;

        $table = self::table_name();
        $city_slug = sanitize_title($city_slug);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE city_slug = %s LIMIT 1",
            $city_slug
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_row_by_id(int $id): ?array
    {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Load the minimal row data needed by the sheet sync to determine which
     * rows are protected (have a custom override) and to compute deletions.
     *
     * @return list<array{id: int, city_slug: string, custom_post_id: int}>
     */
    public static function get_all_rows_for_sync(): array
    {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, city_slug, custom_post_id FROM {$table}",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = [
                'id'             => (int) ($r['id'] ?? 0),
                'city_slug'      => (string) ($r['city_slug'] ?? ''),
                'custom_post_id' => (int) ($r['custom_post_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Find the location row that has been mapped to a given post ID.
     * Only rows with override_type = 'mapped' are considered (map_post action).
     *
     * @return array<string, mixed>|null
     */
    public static function get_location_by_post_id(int $post_id): ?array
    {
        global $wpdb;

        $table = self::table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE custom_post_id = %d AND override_type = 'mapped' LIMIT 1",
            $post_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    // ── PageSpeed cache ──────────────────────────────────────────────────────

    public static function pagespeed_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'seo_pagespeed_cache';
    }

    /**
     * @return array<string, mixed>|null  Row with mobile_metrics/desktop_metrics already decoded.
     */
    public static function get_pagespeed_cache(int $location_id): ?array
    {
        global $wpdb;

        $table = self::pagespeed_table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE location_id = %d LIMIT 1", $location_id ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return null;
        }

        $row['mobile_metrics']  = ! empty( $row['mobile_metrics'] )  ? json_decode( (string) $row['mobile_metrics'],  true ) : [];
        $row['desktop_metrics'] = ! empty( $row['desktop_metrics'] ) ? json_decode( (string) $row['desktop_metrics'], true ) : [];

        return $row;
    }

    /**
     * Batch-load pagespeed cache rows.
     *
     * @param  list<int>           $ids
     * @return array<int, array<string, mixed>>  location_id => decoded row
     */
    public static function get_pagespeed_for_locations( array $ids ): array
    {
        global $wpdb;

        $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        $map = [];

        if ( $ids === [] ) {
            return $map;
        }

        $table        = self::pagespeed_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE location_id IN ({$placeholders})", ...$ids ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return $map;
        }

        foreach ( $rows as $row ) {
            $lid                    = (int) $row['location_id'];
            $row['mobile_metrics']  = ! empty( $row['mobile_metrics'] )  ? json_decode( (string) $row['mobile_metrics'],  true ) : [];
            $row['desktop_metrics'] = ! empty( $row['desktop_metrics'] ) ? json_decode( (string) $row['desktop_metrics'], true ) : [];
            $map[ $lid ]            = $row;
        }

        return $map;
    }

    /**
     * Insert or replace a pagespeed cache row.
     *
     * @param array{score:int, metrics:array<string,string>} $mobile
     * @param array{score:int, metrics:array<string,string>} $desktop
     */
    public static function upsert_pagespeed_cache( int $location_id, array $mobile, array $desktop ): void
    {
        global $wpdb;

        $table = self::pagespeed_table_name();

        $wpdb->replace(
            $table,
            [
                'location_id'     => $location_id,
                'mobile_score'    => (int) $mobile['score'],
                'desktop_score'   => (int) $desktop['score'],
                'mobile_metrics'  => wp_json_encode( $mobile['metrics'] ),
                'desktop_metrics' => wp_json_encode( $desktop['metrics'] ),
                'checked_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s' ]
        );
    }

    // ── CQS Cache ────────────────────────────────────────────────────────────

    public static function cqs_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'edp_cqs_cache';
    }

    /** @return array<string, mixed>|null */
    public static function get_cqs_cache( int $location_id ): ?array
    {
        global $wpdb;

        $table = self::cqs_table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE location_id = %d LIMIT 1", $location_id ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return null;
        }

        $row['breakdown'] = ! empty( $row['breakdown'] )
            ? json_decode( (string) $row['breakdown'], true )
            : [];

        return $row;
    }

    /**
     * @param  list<int>                        $ids
     * @return array<int, array<string, mixed>>  location_id => decoded row
     */
    public static function get_cqs_for_locations( array $ids ): array
    {
        global $wpdb;

        $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        $map = [];

        if ( $ids === [] ) {
            return $map;
        }

        $table        = self::cqs_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE location_id IN ({$placeholders})", ...$ids ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return $map;
        }

        foreach ( $rows as $row ) {
            $lid              = (int) $row['location_id'];
            $row['breakdown'] = ! empty( $row['breakdown'] )
                ? json_decode( (string) $row['breakdown'], true )
                : [];
            $map[ $lid ]      = $row;
        }

        return $map;
    }

    public static function upsert_cqs_cache( int $location_id, int $score, array $breakdown ): void
    {
        global $wpdb;

        $wpdb->replace(
            self::cqs_table_name(),
            [
                'location_id' => $location_id,
                'score'       => $score,
                'breakdown'   => wp_json_encode( $breakdown ),
                'analyzed_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s' ]
        );
    }
}
