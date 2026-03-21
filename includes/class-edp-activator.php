<?php
/**
 * Activation: custom table + rewrite flush.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Activator
{
    public const DB_VERSION = '1.0.0';
    public const OPTION_DB_VERSION = 'edp_seo_db_version';

    public static function activate(): void
    {
        self::create_tables();
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
        flush_rewrite_rules(false);
        update_option(
            EDP_Rewrite::OPTION_REWRITE_VERSION,
            defined('EDP_PLUGIN_VERSION') ? (string) EDP_PLUGIN_VERSION : '0',
            false
        );
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = EDP_Database::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            state_slug varchar(191) NOT NULL,
            state_name varchar(191) NOT NULL,
            state_id char(2) NOT NULL,
            city_slug varchar(191) NOT NULL,
            city_name varchar(191) NOT NULL,
            zips longtext NOT NULL,
            county_fips varchar(32) DEFAULT NULL,
            county_name varchar(191) DEFAULT NULL,
            county_names_all text DEFAULT NULL,
            county_fips_all text DEFAULT NULL,
            custom_post_id bigint(20) unsigned DEFAULT NULL,
            override_type varchar(20) DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY state_city (state_id, city_slug),
            KEY state_slug (state_slug),
            KEY state_city_lookup (state_slug, city_slug)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
