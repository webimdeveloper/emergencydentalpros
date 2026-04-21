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
    /** @var array<int, array<string, mixed>> Per-request cache keyed by location id. */
    private static array $cache = [];

    /**
     * @param array<string, mixed> $row DB row.
     * @return array{
     *   title: string,
     *   h1: string,
     *   html: string,
     *   meta_description: string,
     *   communities_h2: string,
     *   communities_body: string,
     *   other_cities_h2: string,
     *   source: string,
     *   faq: array<string, mixed>
     * }
     */
    public static function resolve_city(array $row): array
    {
        $location_id = (int) ($row['id'] ?? 0);

        if ($location_id > 0 && isset(self::$cache[$location_id])) {
            return self::$cache[$location_id];
        }

        $settings  = EDP_Settings::get_all();
        $templates = $settings['templates']['city_landing'] ?? [];
        $base      = EDP_Template_Engine::base_vars();
        $vars      = EDP_Template_Engine::context_from_city_row($base, $row);

        // Global template defaults (variables resolved).
        $default_title       = EDP_Template_Engine::replace((string) ($templates['meta_title']      ?? ''), $vars);
        $default_h1          = EDP_Template_Engine::replace((string) ($templates['h1']              ?? ''), $vars);
        $default_subtitle    = EDP_Template_Engine::replace((string) ($templates['subtitle']        ?? ''), $vars);
        $default_body        = EDP_Template_Engine::replace((string) ($templates['body']            ?? ''), $vars);
        $default_meta_desc   = EDP_Template_Engine::replace((string) ($templates['meta_description']?? ''), $vars);
        $default_comm_h2     = EDP_Template_Engine::replace((string) ($templates['communities_h2']  ?? ''), $vars);
        $default_comm_body   = EDP_Template_Engine::replace((string) ($templates['communities_body']?? ''), $vars);
        $default_other_h2    = EDP_Template_Engine::replace((string) ($templates['other_cities_h2'] ?? ''), $vars);

        $global_faq = self::build_global_faq($templates, $vars);

        $post_id = isset($row['custom_post_id']) ? (int) $row['custom_post_id'] : 0;

        if ($post_id > 0) {
            $post = get_post($post_id);

            if ($post instanceof WP_Post && $post->post_status === 'publish') {
                $allowed = in_array($post->post_type, ['page', 'post', EDP_CPT::POST_TYPE], true);

                if ($allowed) {
                    $is_cpt = $post->post_type === EDP_CPT::POST_TYPE;

                    $title = $is_cpt ? $default_title : get_the_title($post);
                    $html  = apply_filters('the_content', $post->post_content);
                    $h1    = $is_cpt ? $default_h1 : $title;
                    $faq   = $is_cpt
                        ? self::resolve_cpt_faq($post->ID, $global_faq)
                        : $global_faq;

                    // CPT per-page overrides — empty meta falls back to template default.
                    if ($is_cpt) {
                        $meta_title = (string) get_post_meta($post->ID, '_edp_meta_title', true);
                        if ($meta_title !== '') {
                            $title = $meta_title;
                        }

                        $h1_override = (string) get_post_meta($post->ID, '_edp_h1', true);
                        if ($h1_override !== '') {
                            $h1 = $h1_override;
                        }

                        $meta_desc_override = (string) get_post_meta($post->ID, '_edp_meta_description', true);
                        $meta_desc = $meta_desc_override !== '' ? $meta_desc_override : $default_meta_desc;

                        $comm_h2_override = (string) get_post_meta($post->ID, '_edp_communities_h2', true);
                        $comm_h2 = $comm_h2_override !== '' ? $comm_h2_override : $default_comm_h2;

                        $comm_body_override = (string) get_post_meta($post->ID, '_edp_communities_body', true);
                        $comm_body = $comm_body_override !== '' ? $comm_body_override : $default_comm_body;

                        $other_h2_override = (string) get_post_meta($post->ID, '_edp_other_cities_h2', true);
                        $other_h2 = $other_h2_override !== '' ? $other_h2_override : $default_other_h2;
                    } else {
                        $meta_desc = $default_meta_desc;
                        $comm_h2   = $default_comm_h2;
                        $comm_body = $default_comm_body;
                        $other_h2  = $default_other_h2;
                    }

                    $result = [
                        'title'            => $title,
                        'h1'               => $h1,
                        'subtitle'         => $default_subtitle,
                        'html'             => $html,
                        'meta_description' => $meta_desc,
                        'communities_h2'   => $comm_h2,
                        'communities_body' => $comm_body,
                        'other_cities_h2'  => $other_h2,
                        'source'           => $is_cpt ? 'cpt' : 'mapped',
                        'faq'              => $faq,
                    ];

                    if ($location_id > 0) {
                        self::$cache[$location_id] = $result;
                    }

                    return $result;
                }
            }
        }

        $result = [
            'title'            => $default_title,
            'h1'               => $default_h1,
            'subtitle'         => $default_subtitle,
            'html'             => $default_body,
            'meta_description' => $default_meta_desc,
            'communities_h2'   => $default_comm_h2,
            'communities_body' => $default_comm_body,
            'other_cities_h2'  => $default_other_h2,
            'source'           => 'global',
            'faq'              => $global_faq,
        ];

        if ($location_id > 0) {
            self::$cache[$location_id] = $result;
        }

        return $result;
    }

    /**
     * @param array<string, mixed>  $templates
     * @param array<string, string> $vars
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
     * @param array<string, mixed> $global_faq
     * @return array<string, mixed>
     */
    private static function resolve_cpt_faq(int $post_id, array $global_faq): array
    {
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
            'h2'      => $h2    !== '' ? $h2    : $global_faq['h2'],
            'intro'   => $intro !== '' ? $intro : $global_faq['intro'],
            'items'   => count($items) > 0 ? $items : $global_faq['items'],
        ];
    }
}
