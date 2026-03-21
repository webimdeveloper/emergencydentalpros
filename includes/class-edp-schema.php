<?php
/**
 * JSON-LD for city landing pages.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Schema
{
    /**
     * @param array<string, mixed> $row Location row.
     * @param string               $page_title Resolved page title for schema name.
     */
    public static function output_city_schema(array $row, string $page_title): void
    {
        $settings = EDP_Settings::get_all();
        $business = isset($settings['business_name']) ? (string) $settings['business_name'] : get_bloginfo('name');

        $zips = [];
        if (!empty($row['zips'])) {
            $decoded = json_decode((string) $row['zips'], true);
            if (is_array($decoded)) {
                $zips = array_map('strval', $decoded);
            }
        }

        $city_name = isset($row['city_name']) ? (string) $row['city_name'] : '';
        $state_id = isset($row['state_id']) ? strtoupper((string) $row['state_id']) : '';

        $area_served = [
            [
                '@type' => 'City',
                'name' => $city_name . ', ' . $state_id,
            ],
        ];

        foreach ($zips as $zip) {
            $area_served[] = [
                '@type' => 'PostalCode',
                'postalCode' => $zip,
            ];
        }

        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'Dentist',
            'name' => $page_title !== '' ? $page_title : $business,
            'url' => home_url(
                user_trailingslashit(
                    'locations/' . rawurlencode((string) $row['state_slug']) . '/' . rawurlencode((string) $row['city_slug'])
                )
            ),
            'areaServed' => $area_served,
        ];

        $graph = apply_filters('edp_seo_localbusiness_schema', $graph, $row);

        echo '<script type="application/ld+json">' . wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
