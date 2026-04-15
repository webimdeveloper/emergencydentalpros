<?php
/**
 * Google Sheets CSV fetcher.
 *
 * Converts a Google Sheets share/edit URL to its CSV export endpoint and
 * fetches the raw CSV content via wp_remote_get. No API key required — the
 * sheet must be shared as "Anyone with the link can view".
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Sheet_Fetcher
{
    /**
     * Convert a Google Sheets URL to its CSV export URL.
     *
     * Supports:
     *   https://docs.google.com/spreadsheets/d/{ID}/edit#gid={GID}
     *   https://docs.google.com/spreadsheets/d/{ID}/edit?usp=sharing
     *   https://docs.google.com/spreadsheets/d/{ID}/
     *
     * @return string|null CSV export URL, or null if input is not a valid Sheets URL.
     */
    public static function to_csv_url(string $url): ?string
    {
        if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return null;
        }

        $id = $m[1];

        // Extract optional gid from fragment (#gid=123) or query (?gid=123 / &gid=123).
        $gid = '0';
        if (preg_match('/[#&?]gid=(\d+)/', $url, $g)) {
            $gid = $g[1];
        }

        return 'https://docs.google.com/spreadsheets/d/' . rawurlencode($id)
            . '/export?format=csv&gid=' . rawurlencode($gid);
    }

    /**
     * Fetch CSV content from a Google Sheets share URL.
     *
     * @param string $url Google Sheets share/edit URL.
     * @return string|WP_Error Raw CSV string on success, WP_Error on failure.
     */
    public static function fetch_csv(string $url)
    {
        $csv_url = self::to_csv_url($url);

        if ($csv_url === null) {
            return new WP_Error(
                'invalid_url',
                __('Not a valid Google Sheets URL. Paste the full URL from the address bar (docs.google.com/spreadsheets/…).', 'emergencydentalpros')
            );
        }

        $response = wp_remote_get(
            $csv_url,
            [
                'timeout'     => 30,
                'redirection' => 5,
                'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'http_error',
                sprintf(
                    /* translators: %d: HTTP response code */
                    __('Google Sheets returned HTTP %d. Make sure the sheet is shared as "Anyone with the link can view".', 'emergencydentalpros'),
                    $code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);

        if ($body === '') {
            return new WP_Error(
                'empty_response',
                __('Google Sheets returned an empty response.', 'emergencydentalpros')
            );
        }

        return $body;
    }
}
