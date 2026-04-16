<?php
/**
 * Service account credentials for the Google Sheets API.
 *
 * Stores client_email and private_key extracted from a downloaded
 * service-account JSON key file. Written once via the admin Import screen.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Sheet_Credentials {

	public const OPTION_KEY = 'edp_sheet_sa_credentials';

	/** @return array{client_email: string, private_key: string} */
	public static function get(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			return [ 'client_email' => '', 'private_key' => '' ];
		}
		return [
			'client_email' => (string) ( $saved['client_email'] ?? '' ),
			'private_key'  => (string) ( $saved['private_key'] ?? '' ),
		];
	}

	public static function get_client_email(): string {
		return self::get()['client_email'];
	}

	public static function get_private_key(): string {
		return self::get()['private_key'];
	}

	public static function is_configured(): bool {
		$c = self::get();
		return $c['client_email'] !== '' && $c['private_key'] !== '';
	}

	/**
	 * Parse a service-account JSON string and store client_email + private_key.
	 *
	 * @return true|WP_Error
	 */
	public static function save_from_json( string $json ): bool|WP_Error {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', 'The file is not valid JSON.' );
		}

		if ( empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
			return new WP_Error(
				'missing_fields',
				'JSON must contain "client_email" and "private_key". Make sure you uploaded the correct service account key file.'
			);
		}

		$email = sanitize_email( (string) $data['client_email'] );
		$key   = (string) $data['private_key'];

		if ( $email === '' ) {
			return new WP_Error( 'bad_email', 'client_email in the JSON is not a valid email address.' );
		}

		if ( strpos( $key, '-----BEGIN' ) === false ) {
			return new WP_Error( 'bad_key', 'private_key does not look like a valid PEM key.' );
		}

		update_option(
			self::OPTION_KEY,
			[
				'client_email' => $email,
				'private_key'  => $key,
			],
			false
		);

		return true;
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
