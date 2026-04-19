<?php
/**
 * PageSpeed Insights API v5 client.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Pagespeed_Client {

	const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/** Score thresholds (inclusive lower bound). */
	const THRESHOLD_OK        = 90;
	const THRESHOLD_ATTENTION = 50;

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Run a PageSpeed check for the given URL and strategy.
	 *
	 * @param string $url      Full public URL to test.
	 * @param string $strategy 'mobile' | 'desktop'
	 * @return array{score:int, metrics:array<string,string>}|WP_Error
	 */
	public function check( string $url, string $strategy = 'mobile' ) {
		$endpoint = add_query_arg(
			[
				'url'      => rawurlencode( $url ),
				'strategy' => $strategy,
				'key'      => $this->api_key,
				'fields'   => implode( ',', [
					'lighthouseResult.categories.performance.score',
					'lighthouseResult.audits.largest-contentful-paint.displayValue',
					'lighthouseResult.audits.total-blocking-time.displayValue',
					'lighthouseResult.audits.cumulative-layout-shift.displayValue',
					'lighthouseResult.audits.first-contentful-paint.displayValue',
					'lighthouseResult.audits.speed-index.displayValue',
				] ),
			],
			self::API_ENDPOINT
		);

		$response = wp_remote_get(
			$endpoint,
			[
				'timeout'     => 70,
				'redirection' => 3,
				'sslverify'   => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : "HTTP {$code}";
			return new WP_Error( 'psi_api_error', $msg );
		}

		$perf_score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;
		if ( $perf_score === null ) {
			return new WP_Error( 'psi_parse_error', 'Performance score missing in API response.' );
		}

		$score  = (int) round( (float) $perf_score * 100 );
		$audits = $data['lighthouseResult']['audits'] ?? [];

		return [
			'score'   => $score,
			'metrics' => [
				'lcp' => (string) ( $audits['largest-contentful-paint']['displayValue'] ?? '—' ),
				'tbt' => (string) ( $audits['total-blocking-time']['displayValue'] ?? '—' ),
				'cls' => (string) ( $audits['cumulative-layout-shift']['displayValue'] ?? '—' ),
				'fcp' => (string) ( $audits['first-contentful-paint']['displayValue'] ?? '—' ),
				'si'  => (string) ( $audits['speed-index']['displayValue'] ?? '—' ),
			],
		];
	}

	/** Returns 'ok' | 'attention' | 'crucial' for a given 0-100 score. */
	public static function status( int $score ): string {
		if ( $score >= self::THRESHOLD_OK ) {
			return 'ok';
		}
		if ( $score >= self::THRESHOLD_ATTENTION ) {
			return 'attention';
		}
		return 'crucial';
	}
}
