<?php
/**
 * Plugin options (templates + business info for schema).
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Settings
{
    public const OPTION_KEY = 'edp_seo_settings';

    /**
     * @return array<string, mixed>
     */
    public static function get_all(): array
    {
        $defaults = self::defaults();
        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $saved);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): void
    {
        $clean = self::sanitize($data);
        update_option(self::OPTION_KEY, $clean);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'business_name' => get_bloginfo('name'),
            'templates' => [
                'states_index' => [
                    'meta_title' => 'Dental service areas by state | {site_name}',
                    'meta_description' => 'Browse emergency dental service areas by state.',
                    'h1' => 'Dental service areas by state',
                    'subtitle' => 'Select your state to browse cities and find a same-day emergency dentist.',
                    'body' => '',
                ],
                'state_cities' => [
                    'meta_title' => 'Emergency dental in {state_name} ({state_short}) | {site_name}',
                    'meta_description' => 'Cities we serve in {state_name}.',
                    'h1' => 'Emergency dental in {state_name}',
                    'subtitle' => 'Browse cities we serve in {state_name}.',
                    'body' => '',
                ],
                'city_landing' => [
                    'meta_title' => 'Emergency Dental in {city_name}, {state_short} - 24 Hour | {site_name}',
                    'meta_description' => 'Emergency dental in {city_name}, {state_name}. Service areas: {list_of_related_zips}.',
                    'h1' => 'Emergency dental in {city_name}, {state_short}',
                    'subtitle' => '',
                    'body' => '<p>We provide emergency dental care in {city_name}, {state_name}. ZIPs: {list_of_related_zips}.</p>',
                    'communities_h2' => 'Communities We Cover in {county_name}',
                    'communities_body' => '<p>We serve patients across {city_name} and the surrounding communities. Service ZIP codes: {list_of_related_zips}.</p>',
                    'other_cities_h2' => 'Other Cities We Serve in {state_name}',
                    'faq_h2' => 'Frequently Asked Questions in {city_name}',
                    'faq_intro' => 'Got questions about emergency dental care in {city_name}? We have answers.',
                    'faq_items' => [],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $defaults = self::defaults();
        $out = $defaults;

        if (isset($data['business_name'])) {
            $out['business_name'] = sanitize_text_field((string) $data['business_name']);
        }

        $contexts = ['states_index', 'state_cities', 'city_landing'];

        foreach ($contexts as $ctx) {
            if (!isset($data['templates'][$ctx]) || !is_array($data['templates'][$ctx])) {
                continue;
            }

            $t = $data['templates'][$ctx];
            $out['templates'][$ctx]['meta_title'] = sanitize_text_field((string) ($t['meta_title'] ?? $defaults['templates'][$ctx]['meta_title']));
            $out['templates'][$ctx]['meta_description'] = sanitize_textarea_field((string) ($t['meta_description'] ?? $defaults['templates'][$ctx]['meta_description']));
            $out['templates'][$ctx]['h1'] = sanitize_text_field((string) ($t['h1'] ?? $defaults['templates'][$ctx]['h1']));
            $out['templates'][$ctx]['subtitle'] = sanitize_text_field((string) ($t['subtitle'] ?? $defaults['templates'][$ctx]['subtitle']));
            $out['templates'][$ctx]['body'] = wp_kses_post((string) ($t['body'] ?? $defaults['templates'][$ctx]['body']));

            if ($ctx === 'city_landing') {
                $out['templates'][$ctx]['communities_h2']   = sanitize_text_field((string) ($t['communities_h2'] ?? $defaults['templates'][$ctx]['communities_h2']));
                $out['templates'][$ctx]['communities_body'] = wp_kses_post((string) ($t['communities_body'] ?? $defaults['templates'][$ctx]['communities_body']));
                $out['templates'][$ctx]['other_cities_h2']  = sanitize_text_field((string) ($t['other_cities_h2'] ?? $defaults['templates'][$ctx]['other_cities_h2']));
                $out['templates'][$ctx]['faq_h2']           = sanitize_text_field((string) ($t['faq_h2'] ?? $defaults['templates'][$ctx]['faq_h2']));
                $out['templates'][$ctx]['faq_intro']        = sanitize_text_field((string) ($t['faq_intro'] ?? $defaults['templates'][$ctx]['faq_intro']));

                $raw_items = $t['faq_items'] ?? [];
                if ( is_string( $raw_items ) ) {
                    $raw_items = json_decode( $raw_items, true ) ?? [];
                }
                $clean_items = [];
                if ( is_array( $raw_items ) ) {
                    foreach ( $raw_items as $item ) {
                        if ( ! is_array( $item ) ) {
                            continue;
                        }
                        $q = sanitize_text_field( (string) ( $item['q'] ?? '' ) );
                        if ( $q === '' ) {
                            continue;
                        }
                        $clean_items[] = [
                            'q' => $q,
                            'a' => wp_kses_post( (string) ( $item['a'] ?? '' ) ),
                        ];
                    }
                }
                $out['templates'][$ctx]['faq_items'] = $clean_items;
            }
        }

        return $out;
    }
}
