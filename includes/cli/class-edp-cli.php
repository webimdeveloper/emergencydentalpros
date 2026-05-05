<?php
/**
 * WP-CLI commands.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Import locations from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Absolute path to CSV. Defaults to plugin raw_data.csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp-seo import
	 *     wp edp-seo import /path/to/raw_data.csv
	 *
	 * @param list<string> $args Positional args.
	 */
	$edp_cli_import = static function ( array $args ): void {
		$path = isset( $args[0] ) && is_string( $args[0] ) && $args[0] !== ''
			? $args[0]
			: EDP_PLUGIN_DIR . 'raw_data.csv';

		if ( ! is_readable( $path ) ) {
			WP_CLI::error( 'CSV not readable: ' . $path );
		}

		$result = EDP_Importer::import_from_csv_file( $path );

		if ( ! empty( $result['error'] ) ) {
			WP_CLI::error(
				sprintf(
					'Import failed (%s). Path: %s',
					(string) $result['error'],
					(string) ( $result['path'] ?? $path )
				)
			);
		}

		WP_CLI::success(
			sprintf(
				'Rows: %d — Skipped: %d — Groups: %d',
				(int) ( $result['rows'] ?? 0 ),
				(int) ( $result['skipped'] ?? 0 ),
				(int) ( $result['groups'] ?? 0 )
			)
		);
	};

	WP_CLI::add_command( 'edp-seo import', $edp_cli_import );

	/**
	 * Import Google Places dentist listings for a batch of cities.
	 *
	 * ## OPTIONS
	 *
	 * [--offset=<n>]
	 * : Offset into the locations table (by id order). Default: 0
	 *
	 * [--limit=<n>]
	 * : Number of cities to process. Default: 50
	 *
	 * [--search-only]
	 * : Only run Text Search (no per-business Details). Faster and fewer API calls; hours and phone will be empty.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp-seo import-google --offset=0 --limit=100
	 *     wp edp-seo import-google --offset=100 --limit=100 --search-only
	 *
	 * @param list<string> $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	$edp_cli_google = static function ( array $args, array $assoc_args ): void {
		$offset = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
		$offset = max( 0, $offset );
		$limit  = max( 1, min( 300, $limit ) );

		$fetch_details = ! isset( $assoc_args['search-only'] );

		$result = EDP_Google_Places_Importer::import_batch( $offset, $limit, $fetch_details );

		if ( empty( $result['ok'] ) ) {
			WP_CLI::error(
				isset( $result['error'] )
					? 'Google import failed: ' . (string) $result['error']
					: 'Google import failed.'
			);
		}

		WP_CLI::success(
			sprintf(
				'Cities processed: %d — API calls (approx.): %d',
				(int) ( $result['processed'] ?? 0 ),
				(int) ( $result['api_calls'] ?? 0 )
			)
		);

		if ( ! empty( $result['messages'] ) && is_array( $result['messages'] ) ) {
			foreach ( $result['messages'] as $msg ) {
				WP_CLI::warning( (string) $msg );
			}
		}
	};

	WP_CLI::add_command( 'edp-seo import-google', $edp_cli_google );

	/**
	 * Run one Google Places Text Search to verify API key (does not write to DB).
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp-seo test-google
	 */
	$edp_cli_test_google = static function (): void {
		$result = EDP_Google_Places_Importer::test_api_connection();

		if ( empty( $result['ok'] ) ) {
			WP_CLI::error( (string) ( $result['message'] ?? 'Test failed.' ) );
		}

		WP_CLI::success( (string) ( $result['message'] ?? 'OK' ) );

		if ( isset( $result['api_calls'] ) ) {
			WP_CLI::log( 'API calls: ' . (int) $result['api_calls'] );
		}
	};

	WP_CLI::add_command( 'edp-seo test-google', $edp_cli_test_google );

	/**
	 * Flush WordPress rewrite rules and report the active URL mode.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp flush-rules
	 */
	WP_CLI::add_command( 'edp flush-rules', static function (): void {
		flush_rewrite_rules( false );
		$mode = EDP_Rewrite::get_url_mode();
		WP_CLI::success( 'Rewrite rules flushed. Active URL mode: ' . $mode );
	} );

	/**
	 * List city slugs that conflict with existing WordPress pages.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp check-conflicts
	 */
	WP_CLI::add_command( 'edp check-conflicts', static function (): void {
		if ( EDP_Rewrite::get_url_mode() !== 'flat' ) {
			WP_CLI::warning( 'URL mode is not flat — conflict checking only applies in flat mode.' );
			return;
		}

		global $wpdb;
		$table = EDP_Database::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, city_name, city_slug, state_name FROM {$table} ORDER BY state_name, city_name", ARRAY_A );

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			WP_CLI::log( 'No locations in database.' );
			return;
		}

		$slugs = array_column( $rows, 'city_slug' );
		$conflict_map = EDP_Database::find_wp_slug_conflicts_bulk( $slugs );

		if ( empty( $conflict_map ) ) {
			WP_CLI::success( 'No conflicts found (' . count( $rows ) . ' locations checked).' );
			return;
		}

		$table_data = [];
		foreach ( $rows as $row ) {
			$slug = (string) ( $row['city_slug'] ?? '' );
			if ( ! isset( $conflict_map[ $slug ] ) ) {
				continue;
			}
			$post = $conflict_map[ $slug ];
			$table_data[] = [
				'id'         => (string) ( $row['id'] ?? '' ),
				'city'       => (string) ( $row['city_name'] ?? '' ),
				'state'      => (string) ( $row['state_name'] ?? '' ),
				'slug'       => $slug,
				'wp_post_id' => (string) ( $post->ID ?? '' ),
				'wp_title'   => (string) ( $post->post_title ?? '' ),
				'wp_status'  => (string) ( $post->post_status ?? '' ),
			];
		}

		WP_CLI\Utils\format_items( 'table', $table_data, [ 'id', 'city', 'state', 'slug', 'wp_post_id', 'wp_title', 'wp_status' ] );
		WP_CLI::warning( count( $conflict_map ) . ' conflict(s) found out of ' . count( $rows ) . ' locations.' );
	} );

	/**
	 * Switch the URL mode and flush rewrite rules.
	 *
	 * ## OPTIONS
	 *
	 * <mode>
	 * : URL mode: flat or hierarchical.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp set-url-mode flat
	 *     wp edp set-url-mode hierarchical
	 *
	 * @param list<string> $args Positional args.
	 */
	WP_CLI::add_command( 'edp set-url-mode', static function ( array $args ): void {
		$mode = isset( $args[0] ) ? strtolower( trim( (string) $args[0] ) ) : '';

		if ( ! in_array( $mode, [ 'flat', 'hierarchical' ], true ) ) {
			WP_CLI::error( 'Mode must be flat or hierarchical.' );
		}

		$settings = EDP_Settings::get_all();
		$settings['url_mode'] = $mode;
		EDP_Settings::save( $settings );
		flush_rewrite_rules( false );
		WP_CLI::success( 'URL mode set to ' . $mode . '. Rewrite rules flushed.' );
	} );

	/**
	 * Migrate a conflicting WP page for a location (draft old page, create CPT).
	 *
	 * ## OPTIONS
	 *
	 * <location_id>
	 * : Location row ID.
	 *
	 * [--dry-run]
	 * : Show what would happen without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp edp migrate 42
	 *     wp edp migrate 42 --dry-run
	 *
	 * @param list<string> $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	WP_CLI::add_command( 'edp migrate', static function ( array $args, array $assoc_args ): void {
		$location_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

		if ( $location_id <= 0 ) {
			WP_CLI::error( 'Provide a valid location ID.' );
		}

		$row = EDP_Database::get_row_by_id( $location_id );
		if ( ! is_array( $row ) ) {
			WP_CLI::error( 'Location not found: ' . $location_id );
		}

		$city_slug = sanitize_title( (string) ( $row['city_slug'] ?? '' ) );
		$conflicts = EDP_Database::find_wp_slug_conflicts_bulk( [ $city_slug ] );

		if ( empty( $conflicts[ $city_slug ] ) ) {
			WP_CLI::success( 'No conflict found for slug: ' . $city_slug );
			return;
		}

		$conflict_post = $conflicts[ $city_slug ];
		$is_dry = isset( $assoc_args['dry-run'] );

		WP_CLI::log( 'Location : ' . (string) ( $row['city_name'] ?? '' ) . ', ' . (string) ( $row['state_name'] ?? '' ) );
		WP_CLI::log( 'Slug     : /' . $city_slug . '/' );
		WP_CLI::log( 'Conflict : [' . (string) ( $conflict_post->ID ?? '' ) . '] ' . (string) ( $conflict_post->post_title ?? '' ) . ' (' . (string) ( $conflict_post->post_status ?? '' ) . ')' );

		if ( $is_dry ) {
			WP_CLI::log( '-- Dry run. No changes made.' );
			return;
		}

		// Snapshot content before any DB change.
		$imported_body  = $conflict_post->post_content;
		$pre_meta_title = (string) get_post_meta( (int) $conflict_post->ID, '_yoast_wpseo_title', true )
			?: (string) get_post_meta( (int) $conflict_post->ID, 'rank_math_title', true );
		$pre_meta_desc  = (string) get_post_meta( (int) $conflict_post->ID, '_yoast_wpseo_metadesc', true )
			?: (string) get_post_meta( (int) $conflict_post->ID, 'rank_math_description', true );

		// Draft old page AND rename its slug so the city slug is freed immediately.
		wp_update_post( [
			'ID'          => (int) $conflict_post->ID,
			'post_status' => 'draft',
			'post_name'   => $city_slug . '--migrated',
		] );

		$post_id = wp_insert_post( [
			'post_type'    => EDP_CPT::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => (string) ( $row['city_name'] ?? '' ),
			'post_name'    => $city_slug,
			'post_content' => $imported_body,
		] );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_update_post( [
				'ID'          => (int) $conflict_post->ID,
				'post_status' => $conflict_post->post_status,
				'post_name'   => $city_slug,
			] );
			WP_CLI::error( 'Failed to create CPT.' );
		}

		update_post_meta( (int) $post_id, '_edp_location_id', $location_id );
		update_post_meta( (int) $post_id, '_edp_archived_post_id', (int) $conflict_post->ID );
		if ( $imported_body !== '' ) {
			update_post_meta( (int) $post_id, '_edp_body', wp_kses_post( $imported_body ) );
		}
		if ( $pre_meta_title !== '' ) {
			update_post_meta( (int) $post_id, '_edp_meta_title', sanitize_text_field( $pre_meta_title ) );
		}
		if ( $pre_meta_desc !== '' ) {
			update_post_meta( (int) $post_id, '_edp_meta_description', sanitize_textarea_field( $pre_meta_desc ) );
		}

		global $wpdb;
		$wpdb->update(
			EDP_Database::table_name(),
			[ 'custom_post_id' => (int) $post_id, 'override_type' => 'cpt' ],
			[ 'id' => $location_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		// Remove from ignored conflicts if previously ignored.
		$ignored = get_option( 'edp_ignored_conflicts', [] );
		if ( is_array( $ignored ) && in_array( $city_slug, $ignored, true ) ) {
			update_option( 'edp_ignored_conflicts', array_values( array_diff( $ignored, [ $city_slug ] ) ) );
		}

		flush_rewrite_rules( false );

		WP_CLI::success( 'Migrated. CPT ID: ' . (int) $post_id . '. Old page archived as draft (' . $city_slug . '--migrated).' );
	} );
}
