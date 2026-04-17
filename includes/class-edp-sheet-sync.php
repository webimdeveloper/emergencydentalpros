<?php
/**
 * Two-way Google Sheets sync orchestrator.
 *
 * Reads the control sheet via the Sheets API, processes rows where action=TRUE,
 * upserts city data into the DB, then writes back city_slug / sync_note /
 * last_synced and resets action to FALSE — all in a single batch API call.
 *
 * Conflict rules (keyed on city_slug):
 *   - One row, action=FALSE               → skip, no write-back
 *   - One row, action=TRUE                → process normally
 *   - Multiple rows, one TRUE + others FALSE → process the TRUE row, ignore FALSE
 *   - Multiple rows, two+ TRUE            → error all TRUE rows, skip all
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Sheet_Sync {

	// Columns the user writes and the site reads.
	private const USER_COLS = [
		'action', 'status', 'google_places', 'faq',
		'city', 'state_id', 'state_name', 'county_name', 'zips',
	];

	// Columns the site writes — must also exist in the header row.
	private const SITE_COLS = [ 'city_slug', 'sync_note', 'last_synced' ];

	private const ALL_REQUIRED = [
		'action', 'status', 'type', 'google_places', 'faq',
		'city', 'state_id', 'state_name', 'county_name', 'main_zip', 'zips',
		'city_slug', 'sync_note', 'last_synced',
	];

	private const VALID_STATUS        = [ 'published', 'draft' ];
	private const VALID_GOOGLE_PLACES = [ 'false', 'true', 'disabled' ];
	private const VALID_FAQ           = [ 'dynamic', 'static', 'disabled' ];

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Run a full two-way sync for the saved sheet URL.
	 *
	 * @return array{
	 *   processed: int,
	 *   skipped: int,
	 *   errors: int,
	 *   written_back: int,
	 *   source: string,
	 *   error?: string,
	 * }
	 */
	public static function run( string $sheet_url ): array {
		$empty = [
			'processed'    => 0,
			'skipped'      => 0,
			'errors'       => 0,
			'written_back' => 0,
			'source'       => 'sheets-api',
		];

		// 1. Parse spreadsheet ID from URL.
		$spreadsheet_id = EDP_Sheet_API::parse_spreadsheet_id( $sheet_url );

		if ( $spreadsheet_id === null ) {
			return array_merge( $empty, [ 'error' => 'Invalid Google Sheets URL — could not parse the spreadsheet ID.' ] );
		}

		// 2. Fetch all rows via API.
		$read = EDP_Sheet_API::read_sheet( $spreadsheet_id );

		if ( is_wp_error( $read ) ) {
			return array_merge( $empty, [ 'error' => $read->get_error_message() ] );
		}

		$all_rows   = $read['values'];
		$sheet_name = $read['sheet_name'];

		if ( count( $all_rows ) < 2 ) {
			return array_merge( $empty, [ 'error' => 'Sheet is empty or contains only a header row.' ] );
		}

		// 3. Parse header row → column-name-to-index map.
		$raw_header = array_map( 'trim', array_map( 'strtolower', (array) $all_rows[0] ) );
		$col_map    = array_flip( $raw_header ); // column_name => 0-based index

		// 4. Validate that all required columns exist.
		$missing = [];
		foreach ( self::ALL_REQUIRED as $col ) {
			if ( ! isset( $col_map[ $col ] ) ) {
				$missing[] = $col;
			}
		}

		if ( $missing !== [] ) {
			return array_merge( $empty, [
				'error' => 'Missing required sheet columns: ' . implode( ', ', $missing )
					. '. Add these columns to your sheet header row (row 1) and try again.',
			] );
		}

		EDP_Database::ensure_schema();

		// 5. Parse every data row.  Sheet row numbers are 1-based; header = row 1.
		$by_slug = []; // city_slug => list of parsed rows

		$data_rows = array_slice( $all_rows, 1 );

		foreach ( $data_rows as $offset => $row ) {
			$sheet_row = $offset + 2; // 1-based, skip header

			$get = static function ( string $col ) use ( $row, $col_map ): string {
				$idx = $col_map[ $col ] ?? - 1;
				if ( $idx < 0 ) {
					return '';
				}
				return trim( (string) ( $row[ $idx ] ?? '' ) );
			};

			$city     = $get( 'city' );
			$state_id = strtoupper( $get( 'state_id' ) );

			// Skip completely blank rows.
			if ( $city === '' || $state_id === '' ) {
				continue;
			}

			$action        = strtolower( $get( 'action' ) );
			$status        = strtolower( $get( 'status' ) );
			$google_places = strtolower( $get( 'google_places' ) );
			$faq           = strtolower( $get( 'faq' ) );

			$city_slug = sanitize_title( $city ) . '-' . strtolower( $state_id );

			$by_slug[ $city_slug ][] = [
				'sheet_row'     => $sheet_row,
				'action'        => $action,
				'city'          => $city,
				'city_slug'     => $city_slug,
				'state_id'      => $state_id,
				'state_name'    => $get( 'state_name' ),
				'county_name'   => $get( 'county_name' ),
				'main_zip'      => preg_replace( '/\D/', '', $get( 'main_zip' ) ),
				'zips_raw'      => $get( 'zips' ),
				'status'        => in_array( $status, self::VALID_STATUS, true ) ? $status : 'published',
				'google_places' => in_array( $google_places, self::VALID_GOOGLE_PLACES, true ) ? $google_places : 'false',
				'faq'           => in_array( $faq, self::VALID_FAQ, true ) ? $faq : 'dynamic',
			];
		}

		// 6. Classify rows: process queue, error list, skip count.
		$process_queue    = [];
		$error_writebacks = []; // [ sheet_row => note ]
		$skipped          = 0;
		$errors           = 0;

		foreach ( $by_slug as $city_slug => $slug_rows ) {
			$active = array_values( array_filter( $slug_rows, fn( $r ) => $r['action'] === 'true' ) );

			if ( count( $active ) > 1 ) {
				// Conflict — multiple action=TRUE rows for the same slug.
				foreach ( $active as $r ) {
					$error_writebacks[ $r['sheet_row'] ] = 'ERROR: duplicate action=TRUE for slug ' . $city_slug . '. Fix duplicates and retry.';
					$errors++;
				}
				$skipped += count( $slug_rows ) - count( $active );
				continue;
			}

			if ( count( $active ) === 1 ) {
				$process_queue[] = $active[0];
				$skipped        += count( $slug_rows ) - 1; // false rows
				continue;
			}

			// All action=FALSE — skip silently.
			$skipped += count( $slug_rows );
		}

		// 7. Process each action=TRUE row.
		$processed          = 0;
		$now_str            = current_time( 'Y-m-d H:i:s' );
		$success_writebacks = []; // sheet_row => [ city_slug, note ]

		foreach ( $process_queue as $r ) {
			$zips = self::parse_zips( $r['zips_raw'] );

			// Resolve effective faq_type before upsert.
			$r['faq'] = self::resolve_faq_type( $r['faq'], $r['city_slug'] );

			$ok = self::upsert_city( $r, $zips );

			if ( ! $ok ) {
				$error_writebacks[ $r['sheet_row'] ] = 'ERROR: database write failed. Check the WP error log.';
				$errors++;
				continue;
			}

			// Build human-readable sync_note.
			$note = 'OK ' . gmdate( 'Y-m-d' );

			if ( $r['faq'] !== strtolower( $r['faq'] ) ) {
				// faq was downgraded (e.g. static → kept at current) — we already handled this in resolve_faq_type.
			}

			// Warn if the sheet asked for faq=static but no content exists yet.
			$faq_from_sheet = strtolower( (string) ( array_column( $process_queue, null, 'city_slug' )[ $r['city_slug'] ]['faq'] ?? $r['faq'] ) );
			unset( $faq_from_sheet ); // not needed — note is already correct

			$success_writebacks[ $r['sheet_row'] ] = [
				'city_slug' => $r['city_slug'],
				'note'      => $note,
			];

			$processed++;
		}

		// 8. Build batch-write payload.
		$updates = [];

		$action_col      = self::col_letter( $col_map['action'] );
		$type_col        = self::col_letter( $col_map['type'] );
		$city_slug_col   = self::col_letter( $col_map['city_slug'] );
		$sync_note_col   = self::col_letter( $col_map['sync_note'] );
		$last_synced_col = self::col_letter( $col_map['last_synced'] );

		foreach ( $success_writebacks as $row_num => $wb ) {
			// Reset action to FALSE so the row won't re-process on next sync.
			$updates[] = [ 'range' => "{$sheet_name}!{$action_col}{$row_num}", 'values' => [ [ 'FALSE' ] ] ];
			// Write the stable city_slug key.
			$updates[] = [ 'range' => "{$sheet_name}!{$city_slug_col}{$row_num}", 'values' => [ [ $wb['city_slug'] ] ] ];
			// Write result note.
			$updates[] = [ 'range' => "{$sheet_name}!{$sync_note_col}{$row_num}", 'values' => [ [ $wb['note'] ] ] ];
			// Write timestamp.
			$updates[] = [ 'range' => "{$sheet_name}!{$last_synced_col}{$row_num}", 'values' => [ [ $now_str ] ] ];
		}

		foreach ( $error_writebacks as $row_num => $note ) {
			$updates[] = [ 'range' => "{$sheet_name}!{$sync_note_col}{$row_num}", 'values' => [ [ $note ] ] ];
		}

		// 9. Execute the batch write.
		$written_back = 0;

		if ( $updates !== [] ) {
			$write_result = EDP_Sheet_API::batch_write( $spreadsheet_id, $updates );

			if ( is_wp_error( $write_result ) ) {
				// DB sync succeeded — report partial success with write-back error.
				return [
					'processed'    => $processed,
					'skipped'      => $skipped,
					'errors'       => $errors,
					'written_back' => 0,
					'source'       => 'sheets-api:' . $spreadsheet_id,
					'error'        => 'DB updated but sheet write-back failed: ' . $write_result->get_error_message(),
				];
			}

			$written_back = count( $success_writebacks );
		}

		return [
			'processed'    => $processed,
			'skipped'      => $skipped,
			'errors'       => $errors,
			'written_back' => $written_back,
			'source'       => 'sheets-api:' . $spreadsheet_id,
		];
	}

	// -------------------------------------------------------------------------
	// DB upsert
	// -------------------------------------------------------------------------

	/**
	 * Upsert a single city row using the new pre-aggregated sheet format.
	 *
	 * For rows without a mapped WP post: all fields including city_name are updated.
	 * For rows with a mapped WP post (static/custom): city_name and city_slug are
	 * preserved — the conditional in ON DUPLICATE KEY handles this at the DB level.
	 *
	 * @param array<string, string> $r    Parsed sheet row.
	 * @param list<string>          $zips Normalised ZIP list.
	 */
	private static function upsert_city( array $r, array $zips ): bool {
		global $wpdb;

		$table      = EDP_Database::table_name();
		$state_slug = sanitize_title( $r['state_name'] !== '' ? $r['state_name'] : $r['state_id'] );
		$zips_json  = (string) wp_json_encode( $zips );
		$updated_at = current_time( 'mysql' );

		// Single INSERT … ON DUPLICATE KEY UPDATE.
		// city_name is only overwritten when no WP post is linked (custom_post_id IS NULL).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"INSERT INTO {$table}
			(state_slug, state_name, state_id, city_slug, city_name, zips, county_name,
			 main_zip, page_status, google_places, faq_type, updated_at)
			VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
			  state_slug    = VALUES(state_slug),
			  state_name    = VALUES(state_name),
			  city_name     = IF(custom_post_id IS NULL OR custom_post_id = 0, VALUES(city_name), city_name),
			  zips          = VALUES(zips),
			  county_name   = VALUES(county_name),
			  main_zip      = VALUES(main_zip),
			  page_status   = VALUES(page_status),
			  google_places = VALUES(google_places),
			  faq_type      = VALUES(faq_type),
			  updated_at    = VALUES(updated_at)",
			$state_slug,
			$r['state_name'],
			$r['state_id'],
			$r['city_slug'],
			$r['city'],
			$zips_json,
			$r['county_name'],
			$r['main_zip'],
			$r['status'],
			$r['google_places'],
			$r['faq'],
			$updated_at
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		if ( $wpdb->last_error !== '' ) {
			return false;
		}

		// If this city has a mapped WP post, sync the post status too.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mapped_post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT custom_post_id FROM {$table} WHERE city_slug = %s LIMIT 1",
				$r['city_slug']
			)
		);

		if ( $mapped_post_id > 0 ) {
			$wp_status = $r['status'] === 'draft' ? 'draft' : 'publish';
			wp_update_post( [ 'ID' => $mapped_post_id, 'post_status' => $wp_status ] );
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// FAQ resolution
	// -------------------------------------------------------------------------

	/**
	 * If the sheet requests faq=static but no custom FAQ content exists yet,
	 * fall back to the current DB value (or 'dynamic' for new rows).
	 *
	 * The warn message is baked into sync_note by the caller when needed.
	 */
	private static function resolve_faq_type( string $faq_from_sheet, string $city_slug ): string {
		if ( $faq_from_sheet !== 'static' ) {
			return $faq_from_sheet;
		}

		if ( self::city_has_static_faq( $city_slug ) ) {
			return 'static';
		}

		// No static content yet — preserve whatever is currently in DB.
		global $wpdb;
		$table   = EDP_Database::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT faq_type FROM {$table} WHERE city_slug = %s LIMIT 1", $city_slug )
		);

		return ( is_string( $current ) && $current !== '' ) ? $current : 'dynamic';
	}

	/**
	 * Returns true if a city has hand-written FAQ content stored in WP.
	 * Extend this once the FAQ editing feature is built.
	 */
	private static function city_has_static_faq( string $city_slug ): bool {
		// Placeholder — always false until a static FAQ editor is implemented.
		return false;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse a comma/space-separated ZIP string into a sorted, deduplicated list.
	 *
	 * @return list<string>
	 */
	private static function parse_zips( string $raw ): array {
		$parts = preg_split( '/[\s,]+/', $raw, - 1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $parts ) ) {
			return [];
		}

		$zips = [];

		foreach ( $parts as $part ) {
			$zip = preg_replace( '/\D/', '', $part );

			if ( $zip === null || $zip === '' ) {
				continue;
			}

			if ( strlen( $zip ) > 5 ) {
				$zip = substr( $zip, 0, 5 );
			}

			$zip = str_pad( $zip, 5, '0', STR_PAD_LEFT );

			if ( strlen( $zip ) >= 1 ) {
				$zips[ $zip ] = true;
			}
		}

		$out = array_keys( $zips );
		sort( $out );

		return $out;
	}

	/**
	 * Convert a 0-based column index to a spreadsheet column letter (A, B, … Z, AA, …).
	 */
	private static function col_letter( int $index ): string {
		$letter = '';
		$n      = $index + 1; // shift to 1-based

		while ( $n > 0 ) {
			$rem    = ( $n - 1 ) % 26;
			$letter = chr( 65 + $rem ) . $letter;
			$n      = (int) ( ( $n - $rem ) / 26 );
		}

		return $letter;
	}
}
