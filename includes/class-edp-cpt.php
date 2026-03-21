<?php
/**
 * Hidden CPT for city content overrides.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_CPT
{
    public const POST_TYPE = 'edp_seo_city';

    public static function register(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => __('EDP City Overrides', 'emergencydentalpros'),
                    'singular_name' => __('City Override', 'emergencydentalpros'),
                ],
                'public' => false,
                'publicly_queryable' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'exclude_from_search' => true,
                'has_archive' => false,
                'rewrite' => false,
                'supports' => ['title', 'editor', 'thumbnail'],
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]
        );
    }
}
