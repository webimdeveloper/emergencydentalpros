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
    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'seo_locations';
    }

    public static function count_rows(): int
    {
        global $wpdb;

        $table = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return (int) $count;
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
}
