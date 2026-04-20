<?php
/**
 * Activation: custom table + rewrite flush.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Activator {

	public const DB_VERSION        = '1.5.0';
	public const OPTION_DB_VERSION = 'edp_seo_db_version';

	public static function activate(): void {
		self::create_tables();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
		flush_rewrite_rules( false );
		update_option(
			EDP_Rewrite::OPTION_REWRITE_VERSION,
			defined( 'EDP_PLUGIN_VERSION' ) ? (string) EDP_PLUGIN_VERSION : '0',
			false
		);
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = EDP_Database::table_name();
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
            main_zip varchar(10) NOT NULL DEFAULT '',
            page_status varchar(20) NOT NULL DEFAULT 'published',
            google_places varchar(10) NOT NULL DEFAULT 'false',
            faq_type varchar(10) NOT NULL DEFAULT 'dynamic',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY state_city (state_id, city_slug),
            KEY state_slug (state_slug),
            KEY state_city_lookup (state_slug, city_slug)
        ) {$charset_collate};";

		dbDelta( $sql );

		self::create_nearby_table();
		self::create_pagespeed_table();
		self::create_cqs_table();
	}

	public static function create_nearby_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'seo_nearby_businesses';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            provider varchar(20) NOT NULL DEFAULT 'google',
            external_id varchar(100) NOT NULL,
            sort_order tinyint(3) unsigned NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            rating decimal(3,2) DEFAULT NULL,
            review_count int(10) unsigned DEFAULT NULL,
            phone varchar(64) DEFAULT NULL,
            image_url text,
            hours_text text,
            business_url text,
            fetched_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY loc_provider_ext (location_id, provider, external_id),
            KEY location_id (location_id)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function create_pagespeed_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'seo_pagespeed_cache';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            mobile_score tinyint(3) unsigned DEFAULT NULL,
            desktop_score tinyint(3) unsigned DEFAULT NULL,
            mobile_metrics longtext DEFAULT NULL,
            desktop_metrics longtext DEFAULT NULL,
            checked_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY location_id (location_id)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function create_cqs_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'edp_cqs_cache';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            score tinyint(3) unsigned NOT NULL DEFAULT 0,
            breakdown longtext DEFAULT NULL,
            analyzed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY location_id (location_id)
        ) {$charset_collate};";

		dbDelta( $sql );
	}
}
