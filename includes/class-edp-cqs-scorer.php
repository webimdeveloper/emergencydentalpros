<?php
/**
 * Content Quality Score (CQS) scorer.
 *
 * Scores a location page 0–100 based on plugin-controlled data:
 * post-meta overrides, FAQ settings, Google Business count, etc.
 *
 * Static pages (override_type='cpt') can reach 100.
 * Dynamic (template-only) pages cap at 85 since they miss the
 * "dedicated static page" bonus in two categories.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EDP_Cqs_Scorer {

	/**
	 * Category definitions — name, colour, max points.
	 */
	public const CATEGORIES = [
		'seo_overrides'  => [ 'name' => 'Title & Meta',       'color' => '#7c3aed', 'max' => 20 ],
		'unique_content' => [ 'name' => 'Unique Content',     'color' => '#0ea5e9', 'max' => 25 ],
		'headings'       => [ 'name' => 'Heading Structure',  'color' => '#10b981', 'max' => 15 ],
		'faq_quality'    => [ 'name' => 'FAQ Quality',        'color' => '#f59e0b', 'max' => 15 ],
		'local_data'     => [ 'name' => 'Local Business Data','color' => '#ef4444', 'max' => 15 ],
		'schema'         => [ 'name' => 'Schema markup',      'color' => '#06b6d4', 'max' => 10 ],
	];

	/**
	 * Compute CQS for one location.
	 *
	 * @param  int                  $location_id  Location row ID (used for logging only).
	 * @param  array<string, mixed> $row          Row from the locations table (must include
	 *                                            custom_post_id, override_type, google_count).
	 * @return array{ score: int, breakdown: array<string, array{max:int, earned:int, checks:list<array{text:string,pts:int,pass:bool}>}> }
	 */
	public static function compute( int $location_id, array $row ): array {
		$breakdown = [];
		foreach ( self::CATEGORIES as $key => $def ) {
			$breakdown[ $key ] = [ 'max' => $def['max'], 'earned' => 0, 'checks' => [] ];
		}

		$pid          = (int) ( $row['custom_post_id'] ?? 0 );
		$type         = (string) ( $row['override_type'] ?? '' );
		$post_status  = $pid > 0 ? get_post_status( $pid ) : false;
		$has_static   = $pid > 0 && $type === 'cpt' && $post_status !== false && $post_status !== 'trash';
		$google_count = (int) ( $row['google_count'] ?? 0 );

		// ── Category 1: Title & Meta (20 pts) ────────────────────────────────
		$meta_title = $has_static ? (string) get_post_meta( $pid, '_edp_meta_title', true ) : '';
		$meta_desc  = $has_static ? (string) get_post_meta( $pid, '_edp_meta_description', true ) : '';

		self::check( $breakdown, 'seo_overrides', 'Meta title overridden', 10, $meta_title !== '' );
		self::check( $breakdown, 'seo_overrides', 'Meta description overridden', 10, $meta_desc !== '' );

		// ── Category 2: Unique Content (25 pts) ──────────────────────────────
		$comm_body = $has_static ? (string) get_post_meta( $pid, '_edp_communities_body', true ) : '';

		self::check( $breakdown, 'unique_content', 'Has dedicated static page', 10, $has_static );
		self::check( $breakdown, 'unique_content', 'Communities body text overridden', 15, $comm_body !== '' );

		// ── Category 3: Heading Structure (15 pts) ───────────────────────────
		$h1        = $has_static ? (string) get_post_meta( $pid, '_edp_h1', true ) : '';
		$comm_h2   = $has_static ? (string) get_post_meta( $pid, '_edp_communities_h2', true ) : '';
		$other_h2  = $has_static ? (string) get_post_meta( $pid, '_edp_other_cities_h2', true ) : '';

		self::check( $breakdown, 'headings', 'H1 heading overridden', 8, $h1 !== '' );
		self::check( $breakdown, 'headings', 'Communities H2 overridden', 4, $comm_h2 !== '' );
		self::check( $breakdown, 'headings', 'Other cities H2 overridden', 3, $other_h2 !== '' );

		// ── Category 4: FAQ Quality (15 pts) ─────────────────────────────────
		$faq_raw     = $has_static ? get_post_meta( $pid, '_edp_faq_enabled', true ) : '0';
		$faq_enabled = ( $faq_raw === '' || (int) $faq_raw === 1 );

		$faq_items_raw = $has_static ? (string) get_post_meta( $pid, '_edp_faq_items', true ) : '';
		$faq_items     = [];
		if ( $faq_items_raw !== '' ) {
			$decoded = json_decode( $faq_items_raw, true );
			if ( is_array( $decoded ) ) {
				$faq_items = $decoded;
			}
		}
		$faq_count = count( $faq_items );

		self::check( $breakdown, 'faq_quality', 'FAQ section enabled', 5, $faq_enabled );
		self::check( $breakdown, 'faq_quality', 'Has FAQ items (1+)', 5, $faq_count >= 1 );
		self::check( $breakdown, 'faq_quality', 'Rich FAQ (5+ items)', 5, $faq_count >= 5 );

		// ── Category 5: Local Business Data (15 pts) ─────────────────────────
		self::check( $breakdown, 'local_data', 'Has Google Business data', 10, $google_count >= 1 );
		self::check( $breakdown, 'local_data', '5+ Google Business listings', 5, $google_count >= 5 );

		// ── Category 6: Schema (10 pts) ──────────────────────────────────────
		self::check( $breakdown, 'schema', 'FAQPage JSON-LD active', 5, $faq_enabled && $faq_count >= 1 );
		self::check( $breakdown, 'schema', 'BreadcrumbList JSON-LD (static page)', 5, $has_static );

		// Total.
		$score = 0;
		foreach ( $breakdown as $cat ) {
			$score += $cat['earned'];
		}

		return [ 'score' => $score, 'breakdown' => $breakdown ];
	}

	/**
	 * Grade string from score.
	 */
	public static function grade( int $score ): string {
		if ( $score >= 95 ) { return 'perfect'; }
		if ( $score >= 85 ) { return 'great'; }
		if ( $score >= 75 ) { return 'good'; }
		if ( $score >= 50 ) { return 'average'; }
		return 'poor';
	}

	/**
	 * Human-readable label for a grade.
	 */
	public static function grade_label( string $grade ): string {
		$labels = [
			'perfect' => 'Perfect',
			'great'   => 'Great',
			'good'    => 'Good',
			'average' => 'Average',
			'poor'    => 'Needs Work',
		];
		return $labels[ $grade ] ?? '';
	}

	// ── Internal ─────────────────────────────────────────────────────────────

	/**
	 * @param array<string, array{max:int, earned:int, checks:list<array{text:string,pts:int,pass:bool}>}> $breakdown
	 */
	private static function check( array &$breakdown, string $cat, string $text, int $pts, bool $pass ): void {
		if ( $pass ) {
			$breakdown[ $cat ]['earned'] += $pts;
		}
		$breakdown[ $cat ]['checks'][] = [ 'text' => $text, 'pts' => $pts, 'pass' => $pass ];
	}
}
