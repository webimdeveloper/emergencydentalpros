<?php
/**
 * Google Places API (New) client — Text Search + Place Details + Photo.
 *
 * Endpoints:
 *   POST https://places.googleapis.com/v1/places:searchText
 *   GET  https://places.googleapis.com/v1/places/{id}
 *   GET  https://places.googleapis.com/v1/{photo_name}/media
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Google_Places_Client
{
    private const SEARCH_URL = 'https://places.googleapis.com/v1/places:searchText';
    private const DETAILS_URL = 'https://places.googleapis.com/v1/places/';
    private const PHOTO_URL   = 'https://places.googleapis.com/v1/';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Text Search for businesses.
     * Returns the raw decoded JSON body or WP_Error.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function text_search(string $query, int $limit = 5)
    {
        $limit = max(1, min(20, $limit));

        $body = wp_json_encode([
            'textQuery'      => $query,
            'includedType'   => 'dentist',
            'maxResultCount' => $limit,
        ]);

        $response = wp_remote_post(
            self::SEARCH_URL,
            [
                'timeout' => 20,
                'headers' => [
                    'Content-Type'       => 'application/json',
                    'X-Goog-Api-Key'     => $this->api_key,
                    'X-Goog-FieldMask'   => 'places.id,places.displayName,places.rating,places.userRatingCount,places.photos,places.googleMapsUri',
                ],
                'body' => $body,
            ]
        );

        return $this->parse_response($response);
    }

    /**
     * Place Details for a single place id.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function place_details(string $place_id)
    {
        $url = self::DETAILS_URL . rawurlencode($place_id);

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 20,
                'headers' => [
                    'X-Goog-Api-Key'   => $this->api_key,
                    'X-Goog-FieldMask' => 'id,displayName,nationalPhoneNumber,regularOpeningHours,photos,googleMapsUri,rating,userRatingCount',
                ],
            ]
        );

        return $this->parse_response($response);
    }

    /**
     * Resolve a photo resource name to a stable CDN URL.
     * photo_name format: "places/{place_id}/photos/{photo_id}"
     *
     * Uses skipHttpRedirect=true so the API returns JSON {"photoUri":"https://..."} instead
     * of a 302 redirect, which is more reliable for server-side calls.
     */
    public function resolve_photo_url(string $photo_name, int $max_width = 400): string
    {
        if ($photo_name === '') {
            return '';
        }

        $url = add_query_arg(
            [
                'maxWidthPx'       => $max_width,
                'skipHttpRedirect' => 'true',
                'key'              => $this->api_key,
            ],
            self::PHOTO_URL . ltrim($photo_name, '/') . '/media'
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $uri  = isset($body['photoUri']) && is_string($body['photoUri']) ? $body['photoUri'] : '';

        return str_starts_with($uri, 'https://') ? $uri : '';
    }

    /**
     * @param array<string,mixed>|\WP_Error $response
     * @return array<string, mixed>|\WP_Error
     */
    private function parse_response($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            $detail = mb_substr(wp_strip_all_tags(trim($body)), 0, 300);

            /* Try to extract Google error message from JSON */
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $detail = (string) $decoded['error']['message'];
            }

            return new WP_Error(
                'google_places_http_error',
                sprintf(
                    /* translators: 1: HTTP status, 2: error detail */
                    __('Google Places API error (%1$d): %2$s', 'emergencydentalpros'),
                    $code,
                    $detail
                ),
                ['status' => $code, 'body' => $body]
            );
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return new WP_Error('google_places_json_error', __('Invalid JSON from Google Places API.', 'emergencydentalpros'));
        }

        return $data;
    }
}
