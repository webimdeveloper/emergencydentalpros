<?php
/**
 * Placeholder replacement for SEO templates.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Template_Engine
{
    /**
     * @param array<string, string> $vars
     */
    public static function replace(string $template, array $vars): string
    {
        $vars = apply_filters('edp_seo_replace_vars', $vars, $template);

        $out = $template;

        foreach ($vars as $key => $value) {
            $token = '{' . $key . '}';
            $out = str_replace($token, $value, $out);
        }

        return $out;
    }

    /**
     * @param array<string, string> $base
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function context_from_city_row(array $base, array $data): array
    {
        $zips = [];
        if (!empty($data['zips'])) {
            $decoded = json_decode((string) $data['zips'], true);
            if (is_array($decoded)) {
                $zips = array_map('strval', $decoded);
            }
        }

        sort($zips);

        $base['city_name']  = isset($data['city_name']) ? (string) $data['city_name'] : '';
        $base['state_name'] = isset($data['state_name']) ? (string) $data['state_name'] : '';
        $base['state_short'] = isset($data['state_id']) ? strtoupper((string) $data['state_id']) : '';
        $base['state_slug'] = isset($data['state_slug']) ? sanitize_title((string) $data['state_slug']) : '';
        $base['county_name'] = isset($data['county_name']) ? (string) $data['county_name'] : '';
        $base['main_zip']   = isset($data['main_zip']) ? (string) $data['main_zip'] : '';
        $base['list_of_related_zips'] = implode(', ', $zips);

        return $base;
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $state
     * @return array<string, string>
     */
    public static function context_from_state(array $base, array $state): array
    {
        $base['state_name'] = $state['state_name'] ?? '';
        $base['state_short'] = isset($state['state_id']) ? strtoupper((string) $state['state_id']) : '';
        $base['state_slug'] = isset($state['state_slug']) ? sanitize_title((string) $state['state_slug']) : '';

        return $base;
    }

    /**
     * @return array<string, string>
     */
    public static function base_vars(): array
    {
        $settings = EDP_Settings::get_all();
        $gs       = isset($settings['global_settings']) && is_array($settings['global_settings'])
            ? $settings['global_settings']
            : [];

        $phone_text = isset($gs['phone_text']) && $gs['phone_text'] !== '' ? $gs['phone_text'] : '(855) 407-7377';
        $phone_href = isset($gs['phone_href']) && $gs['phone_href'] !== '' ? $gs['phone_href'] : 'tel:8554077377';
        $feat_url   = isset($gs['featured_img_url']) && $gs['featured_img_url'] !== '' ? $gs['featured_img_url'] : '';
        $hours      = isset($gs['opening_hours']) && $gs['opening_hours'] !== '' ? $gs['opening_hours'] : '24/7';

        return [
            'site_name'      => get_bloginfo('name'),
            'phone_number'   => '<a href="' . esc_url($phone_href) . '" class="ws_inner__cta_link">' . esc_html($phone_text) . '</a>',
            'ws_featured_img' => $feat_url !== '' ? '<img src="' . esc_url($feat_url) . '" alt="" loading="lazy" decoding="async">' : '',
            'opening_hours'  => esc_html($hours),
        ];
    }
}
