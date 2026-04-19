<?php
/**
 * Resolves city page content: mapped post/page > CPT override > global templates.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Content_Resolver
{
    /**
     * @param array<string, mixed> $row DB row.
     * @return array{title: string, html: string, h1: string, source: string, faq: array<string, mixed>}
     */
    public static function resolve_city(array $row): array
    {
        $settings = EDP_Settings::get_all();
        $templates = $settings['templates']['city_landing'] ?? [];
        $base = EDP_Template_Engine::base_vars();
        $vars = EDP_Template_Engine::context_from_city_row($base, $row);

        $default_title = EDP_Template_Engine::replace((string) ($templates['meta_title'] ?? ''), $vars);
        $default_h1 = EDP_Template_Engine::replace((string) ($templates['h1'] ?? ''), $vars);
        $default_body = EDP_Template_Engine::replace((string) ($templates['body'] ?? ''), $vars);

        $global_faq = self::build_global_faq($templates, $vars);

        $post_id = isset($row['custom_post_id']) ? (int) $row['custom_post_id'] : 0;

        if ($post_id > 0) {
            $post = get_post($post_id);

            if ($post instanceof WP_Post && $post->post_status === 'publish') {
                $allowed = in_array($post->post_type, ['page', 'post', EDP_CPT::POST_TYPE], true);

                if ($allowed) {
                    $title = get_the_title($post);
                    $html = apply_filters('the_content', $post->post_content);

                    $h1 = $title;

                    if ($post->post_type === EDP_CPT::POST_TYPE) {
                        $h1 = $default_h1;
                    }

                    $faq = $post->post_type === EDP_CPT::POST_TYPE
                        ? self::resolve_cpt_faq($post->ID, $global_faq)
                        : $global_faq;

                    return [
                        'title'  => $title,
                        'html'   => $html,
                        'h1'     => $h1,
                        'source' => $post->post_type === EDP_CPT::POST_TYPE ? 'cpt' : 'mapped',
                        'faq'    => $faq,
                    ];
                }
            }
        }

        return [
            'title'  => $default_title,
            'html'   => $default_body,
            'h1'     => $default_h1,
            'source' => 'global',
            'faq'    => $global_faq,
        ];
    }

    /**
     * Build FAQ array from global template settings with vars replaced.
     *
     * @param array<string, mixed>  $templates City landing template settings.
     * @param array<string, string> $vars      Template variable map.
     * @return array<string, mixed>
     */
    private static function build_global_faq(array $templates, array $vars): array
    {
        $items = isset($templates['faq_items']) && is_array($templates['faq_items'])
            ? $templates['faq_items']
            : [];

        return [
            'enabled' => count($items) > 0,
            'h2'      => EDP_Template_Engine::replace((string) ($templates['faq_h2']    ?? ''), $vars),
            'intro'   => EDP_Template_Engine::replace((string) ($templates['faq_intro'] ?? ''), $vars),
            'items'   => $items,
        ];
    }

    /**
     * Resolve FAQ for a CPT post — respects the toggle and any per-page overrides.
     *
     * @param array<string, mixed> $global_faq Fallback from global settings.
     * @return array<string, mixed>
     */
    private static function resolve_cpt_faq(int $post_id, array $global_faq): array
    {
        // Default: enabled (meta not set yet on new posts → empty string).
        $enabled_meta = get_post_meta($post_id, '_edp_faq_enabled', true);
        $enabled = ($enabled_meta === '') ? true : (bool) (int) $enabled_meta;

        if (!$enabled) {
            return ['enabled' => false, 'h2' => '', 'intro' => '', 'items' => []];
        }

        $h2    = (string) get_post_meta($post_id, '_edp_faq_h2',    true);
        $intro = (string) get_post_meta($post_id, '_edp_faq_intro', true);

        $items_raw = (string) get_post_meta($post_id, '_edp_faq_items', true);
        $items     = [];
        if ($items_raw !== '') {
            $decoded = json_decode($items_raw, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $items = $decoded;
            }
        }

        return [
            'enabled' => true,
            'h2'      => $h2 !== '' ? $h2 : $global_faq['h2'],
            'intro'   => $intro !== '' ? $intro : $global_faq['intro'],
            'items'   => count($items) > 0 ? $items : $global_faq['items'],
        ];
    }
}
