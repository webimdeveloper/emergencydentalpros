<?php
/**
 * Minimal Yelp Fusion API v3 client (Search + Business Details).
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Yelp_Client
{
    private const SEARCH_URL = 'https://api.yelp.com/v3/businesses/search';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * @return array<string, mixed>|\WP_Error Decoded JSON body
     */
    public function business_search(string $term, string $location, int $limit)
    {
        $limit = max(1, min(50, $limit));

        $url = add_query_arg(
            [
                'term' => $term,
                'location' => $location,
                'limit' => $limit,
                'sort_by' => 'rating',
            ],
            self::SEARCH_URL
        );

        return $this->get_json($url);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function business_details(string $business_id)
    {
        $id = rawurlencode($business_id);

        return $this->get_json('https://api.yelp.com/v3/businesses/' . $id);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function get_json(string $url, int $attempt = 1)
    {
        $ua = apply_filters(
            'edp_seo_yelp_user_agent',
            'EmergencyDentalPros/' . (defined('EDP_PLUGIN_VERSION') ? EDP_PLUGIN_VERSION : '1') . ' WordPress/' . get_bloginfo('version') . ' (+' . home_url('/') . ')'
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 25,
                'redirection' => 2,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                    'User-Agent' => is_string($ua) && $ua !== '' ? $ua : 'WordPress/' . get_bloginfo('version'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code === 429 && $attempt < 4) {
            sleep(min(2 * $attempt, 10));

            return $this->get_json($url, $attempt + 1);
        }

        if ($code < 200 || $code >= 300) {
            $detail = self::format_error_detail($body, $code, $response);

            return new WP_Error(
                'yelp_http_error',
                sprintf(
                    /* translators: 1: HTTP status, 2: Yelp message or body excerpt */
                    __('Yelp API error (%1$d): %2$s', 'emergencydentalpros'),
                    $code,
                    $detail
                ),
                [
                    'status' => $code,
                    'body' => $body,
                    'yelp_error' => self::parse_yelp_error_payload($body),
                ]
            );
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return new WP_Error('yelp_json_error', __('Invalid JSON from Yelp API.', 'emergencydentalpros'));
        }

        return $data;
    }

    /**
     * Human-readable line for logs/admin (prefers Yelp JSON error fields).
     *
     * @param array|null $http_response Response array from wp_remote_get (not WP_Error).
     */
    private static function format_error_detail(string $body, int $code, $http_response = null): string
    {
        $parsed = self::parse_yelp_error_payload($body);

        if ($parsed !== null) {
            $parts = [];

            if ($parsed['description'] !== '') {
                $parts[] = $parsed['description'];
            }

            if ($parsed['code'] !== '') {
                $parts[] = sprintf(
                    /* translators: %s: machine-readable error code from Yelp */
                    __('code %s', 'emergencydentalpros'),
                    $parsed['code']
                );
            }

            if ($parts !== []) {
                return implode(' — ', $parts);
            }
        }

        $trim = trim($body);

        if ($trim === '') {
            $http_phrase = '';

            if (is_array($http_response) && !is_wp_error($http_response)) {
                $phrase = wp_remote_retrieve_response_message($http_response);

                if (is_string($phrase) && $phrase !== '') {
                    $http_phrase = ' — ' . $phrase;
                }
            }

            return sprintf(
                /* translators: 1: HTTP status code, 2: optional reason phrase e.g. Forbidden */
                __('Empty response body (HTTP %1$d%2$s). Often: wrong API key, host firewall blocking outbound HTTPS to api.yelp.com, or a security plugin stripping responses. Confirm key in Yelp app, then ask your host to allow TLS to api.yelp.com.', 'emergencydentalpros'),
                $code,
                $http_phrase
            );
        }

        return mb_substr(wp_strip_all_tags($trim), 0, 400);
    }

    /**
     * Yelp Fusion often returns { "error": { "code": "...", "description": "..." } }.
     *
     * @return array{code:string, description:string}|null
     */
    private static function parse_yelp_error_payload(string $body): ?array
    {
        $trim = trim($body);

        if ($trim === '' || $trim[0] !== '{') {
            return null;
        }

        $data = json_decode($trim, true);

        if (!is_array($data)) {
            return null;
        }

        $out = [
            'code' => '',
            'description' => '',
        ];

        if (isset($data['error']) && is_array($data['error'])) {
            $e = $data['error'];

            if (isset($e['code'])) {
                $out['code'] = sanitize_text_field((string) $e['code']);
            }

            if (isset($e['description'])) {
                $out['description'] = sanitize_text_field((string) $e['description']);
            }

            if ($out['description'] === '' && isset($e['field'])) {
                $out['description'] = sanitize_text_field((string) $e['field']);
            }
        }

        if ($out['description'] === '' && isset($data['message'])) {
            $out['description'] = sanitize_text_field((string) $data['message']);
        }

        if ($out['code'] === '' && $out['description'] === '') {
            return null;
        }

        return $out;
    }
}
