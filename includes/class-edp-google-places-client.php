<?php
/**
 * Minimal Google Places API client (Text Search + Place Details + Photo resolve).
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Google_Places_Client
{
    private const SEARCH_URL  = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
    private const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';
    private const PHOTO_URL   = 'https://maps.googleapis.com/maps/api/place/photo';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Text Search for businesses in a location.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function text_search(string $query, int $limit = 5)
    {
        $limit = max(1, min(20, $limit));

        $url = add_query_arg(
            [
                'query' => $query,
                'type'  => 'dentist',
                'key'   => $this->api_key,
            ],
            self::SEARCH_URL
        );

        $result = $this->get_json($url);

        if (is_wp_error($result)) {
            return $result;
        }

        /* Trim results to the requested limit */
        if (isset($result['results']) && is_array($result['results']) && count($result['results']) > $limit) {
            $result['results'] = array_slice($result['results'], 0, $limit);
        }

        return $result;
    }

    /**
     * Place Details for a single place_id.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function place_details(string $place_id)
    {
        $url = add_query_arg(
            [
                'place_id' => $place_id,
                'fields'   => 'name,formatted_phone_number,opening_hours,photos,url,rating,user_ratings_total',
                'key'      => $this->api_key,
            ],
            self::DETAILS_URL
        );

        return $this->get_json($url);
    }

    /**
     * Resolve a photo_reference to a publicly-cacheable CDN URL (lh3.googleusercontent.com).
     * Makes a HEAD request and reads the Location header — no body download.
     */
    public function resolve_photo_url(string $photo_reference, int $max_width = 400): string
    {
        if ($photo_reference === '') {
            return '';
        }

        $url = add_query_arg(
            [
                'maxwidth'        => $max_width,
                'photo_reference' => $photo_reference,
                'key'             => $this->api_key,
            ],
            self::PHOTO_URL
        );

        $response = wp_remote_head(
            $url,
            [
                'timeout'     => 10,
                'redirection' => 0,
                'headers'     => ['Accept' => 'image/*'],
            ]
        );

        if (is_wp_error($response)) {
            return '';
        }

        $location = (string) wp_remote_retrieve_header($response, 'location');

        if ($location !== '' && str_starts_with($location, 'https://')) {
            return $location;
        }

        return '';
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function get_json(string $url, int $attempt = 1)
    {
        $ua = apply_filters(
            'edp_google_places_user_agent',
            'EmergencyDentalPros/' . (defined('EDP_PLUGIN_VERSION') ? EDP_PLUGIN_VERSION : '1')
                . ' WordPress/' . get_bloginfo('version')
                . ' (+' . home_url('/') . ')'
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 20,
                'redirection' => 2,
                'headers'     => [
                    'Accept'     => 'application/json',
                    'User-Agent' => is_string($ua) && $ua !== '' ? $ua : 'WordPress/' . get_bloginfo('version'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        /* Retry on 429 */
        if ($code === 429 && $attempt < 4) {
            sleep(min(2 * $attempt, 10));

            return $this->get_json($url, $attempt + 1);
        }

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'google_places_http_error',
                sprintf(
                    /* translators: 1: HTTP status, 2: body excerpt */
                    __('Google Places API error (%1$d): %2$s', 'emergencydentalpros'),
                    $code,
                    mb_substr(wp_strip_all_tags(trim($body)), 0, 300)
                ),
                ['status' => $code, 'body' => $body]
            );
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return new WP_Error('google_places_json_error', __('Invalid JSON from Google Places API.', 'emergencydentalpros'));
        }

        /* Google returns status in the body, not in HTTP code */
        $api_status = (string) ($data['status'] ?? 'OK');

        if (!in_array($api_status, ['OK', 'ZERO_RESULTS'], true)) {
            $msg = (string) ($data['error_message'] ?? $api_status);

            return new WP_Error(
                'google_places_api_status',
                sprintf(
                    /* translators: 1: Google API status string, 2: error message */
                    __('Google Places API status %1$s: %2$s', 'emergencydentalpros'),
                    $api_status,
                    $msg
                )
            );
        }

        return $data;
    }
}
