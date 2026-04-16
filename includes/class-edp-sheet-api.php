<?php
/**
 * Google Sheets REST API client.
 *
 * Handles JWT-based service-account auth (no heavy library needed) and
 * exposes two operations used by EDP_Sheet_Sync:
 *   - read_sheet()       fetch all rows from a spreadsheet
 *   - batch_write()      write multiple cell ranges in one API call
 *
 * Access tokens are cached in a transient for 55 minutes.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Sheet_API {

	private const TOKEN_URL      = 'https://oauth2.googleapis.com/token';
	private const SCOPE          = 'https://www.googleapis.com/auth/spreadsheets';
	private const API_BASE       = 'https://sheets.googleapis.com/v4/spreadsheets';
	private const TOKEN_TRANSIENT = 'edp_sheet_api_token';

	// -------------------------------------------------------------------------
	// Public helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse a Google Sheets spreadsheet ID from any share/edit URL.
	 */
	public static function parse_spreadsheet_id( string $url ): ?string {
		if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			return null;
		}
		return $m[1];
	}

	// -------------------------------------------------------------------------
	// Sheet operations
	// -------------------------------------------------------------------------

	/**
	 * Read all rows from a spreadsheet.
	 *
	 * Returns an array with:
	 *   'values'     => list<list<string>>  (rows × columns, first row = headers)
	 *   'sheet_name' => string              (actual sheet tab name, e.g. "Sheet1")
	 *
	 * @return array{values: list<list<string>>, sheet_name: string}|WP_Error
	 */
	public static function read_sheet( string $spreadsheet_id ): array|WP_Error {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// A:Z covers up to 26 columns — enough for our schema.
		$url = sprintf(
			'%s/%s/values/%s?valueRenderOption=FORMATTED_VALUE',
			self::API_BASE,
			rawurlencode( $spreadsheet_id ),
			rawurlencode( 'A:Z' )
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: "HTTP {$code}";
			return new WP_Error( 'read_failed', "Sheet read failed: {$msg}" );
		}

		// The 'range' field looks like "Sheet1!A1:Z100" — extract the sheet name.
		$range_str  = isset( $data['range'] ) ? (string) $data['range'] : '';
		$sheet_name = 'Sheet1'; // sensible default if we can't parse it

		if ( $range_str !== '' && str_contains( $range_str, '!' ) ) {
			$sheet_name = explode( '!', $range_str, 2 )[0];
		}

		$values = $data['values'] ?? [];

		return [
			'values'     => is_array( $values ) ? array_values( $values ) : [],
			'sheet_name' => $sheet_name,
		];
	}

	/**
	 * Write multiple cell ranges in a single batchUpdate call.
	 *
	 * @param list<array{range: string, values: list<list<string>>}> $updates
	 * @return true|WP_Error
	 */
	public static function batch_write( string $spreadsheet_id, array $updates ): bool|WP_Error {
		if ( $updates === [] ) {
			return true;
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = sprintf(
			'%s/%s/values:batchUpdate',
			self::API_BASE,
			rawurlencode( $spreadsheet_id )
		);

		$body = wp_json_encode( [
			'valueInputOption' => 'RAW',
			'data'             => $updates,
		] );

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: "HTTP {$code}";
			return new WP_Error( 'write_failed', "Sheet write failed: {$msg}" );
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Token management
	// -------------------------------------------------------------------------

	/**
	 * Return a valid OAuth2 access token, refreshing via JWT if needed.
	 *
	 * @return string|WP_Error
	 */
	public static function get_access_token(): string|WP_Error {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		if ( ! EDP_Sheet_Credentials::is_configured() ) {
			return new WP_Error(
				'no_credentials',
				'Service account credentials are not configured. Upload the JSON key on the Import screen.'
			);
		}

		$jwt = self::build_jwt();
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'body'    => [
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$desc = is_array( $data ) && isset( $data['error_description'] )
				? (string) $data['error_description']
				: "HTTP {$code}";
			return new WP_Error( 'token_failed', "Token exchange failed: {$desc}" );
		}

		$token = (string) $data['access_token'];
		$ttl   = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 60 ) : 3300;

		set_transient( self::TOKEN_TRANSIENT, $token, $ttl );

		return $token;
	}

	/**
	 * Clear cached access token (e.g., after credential change).
	 */
	public static function clear_token_cache(): void {
		delete_transient( self::TOKEN_TRANSIENT );
	}

	// -------------------------------------------------------------------------
	// JWT signing
	// -------------------------------------------------------------------------

	/** @return string|WP_Error */
	private static function build_jwt(): string|WP_Error {
		$email = EDP_Sheet_Credentials::get_client_email();
		$pem   = EDP_Sheet_Credentials::get_private_key();
		$now   = time();

		$header  = self::base64url( (string) wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$payload = self::base64url( (string) wp_json_encode( [
			'iss'   => $email,
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'exp'   => $now + 3600,
			'iat'   => $now,
		] ) );

		$signing_input = $header . '.' . $payload;

		$pkey = openssl_pkey_get_private( $pem );

		if ( $pkey === false ) {
			return new WP_Error(
				'bad_key',
				'Could not parse the service account private key. Check that the JSON key file was uploaded correctly.'
			);
		}

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256 );

		if ( ! $ok ) {
			return new WP_Error( 'sign_failed', 'JWT signing failed (openssl_sign returned false).' );
		}

		return $signing_input . '.' . self::base64url( $signature );
	}

	private static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
