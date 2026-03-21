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

        $base['city_name'] = isset($data['city_name']) ? (string) $data['city_name'] : '';
        $base['state_name'] = isset($data['state_name']) ? (string) $data['state_name'] : '';
        $base['state_short'] = isset($data['state_id']) ? strtoupper((string) $data['state_id']) : '';
        $base['state_slug'] = isset($data['state_slug']) ? (string) $data['state_slug'] : '';
        $base['state_slug'] = sanitize_title($base['state_slug']);
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
        return [
            'site_name' => get_bloginfo('name'),
        ];
    }
}
