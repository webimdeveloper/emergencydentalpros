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
     * @return array{title: string, html: string, h1: string, source: string}
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

                    return [
                        'title' => $title,
                        'html' => $html,
                        'h1' => $h1,
                        'source' => $post->post_type === EDP_CPT::POST_TYPE ? 'cpt' : 'mapped',
                    ];
                }
            }
        }

        return [
            'title' => $default_title,
            'html' => $default_body,
            'h1' => $default_h1,
            'source' => 'global',
        ];
    }
}
