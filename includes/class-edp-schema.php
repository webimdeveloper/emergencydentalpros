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
        $business = get_bloginfo('name');

        $zips = [];
        if (!empty($row['zips'])) {
            $decoded = json_decode((string) $row['zips'], true);
            if (is_array($decoded)) {
                $zips = array_map('strval', $decoded);
            }
        }

        $city_name  = isset($row['city_name'])  ? (string) $row['city_name']              : '';
        $state_name = isset($row['state_name']) ? (string) $row['state_name']             : '';
        $state_id   = isset($row['state_id'])   ? strtoupper((string) $row['state_id'])   : '';
        $state_slug = isset($row['state_slug']) ? (string) $row['state_slug']             : '';
        $city_slug  = isset($row['city_slug'])  ? (string) $row['city_slug']              : '';

        $page_url = EDP_Rewrite::city_url($row);

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

        $dentist_graph = [
            '@context' => 'https://schema.org',
            '@type'    => 'Dentist',
            'name'     => $business,
            'url'      => $page_url,
            'areaServed' => $area_served,
        ];

        $dentist_graph = apply_filters('edp_seo_localbusiness_schema', $dentist_graph, $row);

        echo '<script type="application/ld+json">' . wp_json_encode($dentist_graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        // BreadcrumbList
        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Home',
                    'item'     => home_url('/'),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => 'Locations',
                    'item'     => EDP_Rewrite::states_url(),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $state_name !== '' ? $state_name : $state_id,
                    'item'     => EDP_Rewrite::state_url($state_slug),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 4,
                    'name'     => $city_name . ($state_id !== '' ? ', ' . $state_id : ''),
                    'item'     => $page_url,
                ],
            ],
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * @param array<string, mixed> $faq_items  Array of ['q'=>string,'a'=>string].
     * @param string               $city_name  Used for speakable cssSelector hint.
     */
    public static function output_faqpage_schema(array $faq_items, string $city_name = ''): void
    {
        if (empty($faq_items)) {
            return;
        }

        $entities = [];

        foreach ($faq_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $q = trim((string) ($item['q'] ?? ''));
            $a = trim(wp_strip_all_tags((string) ($item['a'] ?? '')));

            if ($q === '' || $a === '') {
                continue;
            }

            $entities[] = [
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $a,
                ],
            ];
        }

        if (empty($entities)) {
            return;
        }

        $graph = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
