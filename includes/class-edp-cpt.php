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

    /** Meta keys managed by the location settings metabox. */
    public const META_KEYS = [
        '_edp_meta_title',
        '_edp_meta_description',
        '_edp_h1',
        '_edp_body',
        '_edp_communities_h2',
        '_edp_communities_body',
        '_edp_other_cities_h2',
        '_edp_show_other_cities',
        '_edp_redirect_post_id',
    ];

    public static function register(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name'          => __('EDP City Overrides', 'emergencydentalpros'),
                    'singular_name' => __('City Override', 'emergencydentalpros'),
                ],
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'exclude_from_search' => true,
                'has_archive'         => false,
                'rewrite'             => false,
                'supports'            => ['title', 'editor'],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
            ]
        );

        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'register_metaboxes']);
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'move_publish_to_main'], 20);
        add_action('save_post_' . self::POST_TYPE,      [self::class, 'save_metabox'], 10, 2);
        add_action('admin_head-post.php',               [self::class, 'hide_meta_boxes_css']);
        add_action('admin_head-post-new.php',           [self::class, 'hide_meta_boxes_css']);
        add_filter('tiny_mce_before_init',              [self::class, 'editor_content_style']);
        add_filter('use_block_editor_for_post_type',    [self::class, 'disable_block_editor'], 10, 2);
        // Force single-column layout — ignore any per-user screen option saved for this screen.
        add_filter('get_user_option_screen_layout_' . self::POST_TYPE, '__return_one');
    }

    public static function register_metaboxes(): void
    {
        add_meta_box(
            'edp_location_settings',
            __('Location Page Settings', 'emergencydentalpros'),
            [self::class, 'render_settings_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /** Moves the Publish metabox from the side panel into the main column. */
    public static function move_publish_to_main(): void
    {
        remove_meta_box('submitdiv', self::POST_TYPE, 'side');
        add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', self::POST_TYPE, 'normal', 'high');
    }

    public static function render_settings_metabox(\WP_Post $post): void
    {
        $view = EDP_PLUGIN_DIR . 'admin/views/city-settings-metabox.php';

        if (is_readable($view)) {
            include $view;
        }
    }

    public static function save_metabox(int $post_id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['edp_location_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edp_location_settings_nonce'])),
            'edp_location_settings_' . $post_id
        )) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $text_fields = [
            '_edp_meta_title'     => 'edp_meta_title',
            '_edp_h1'             => 'edp_h1',
            '_edp_communities_h2' => 'edp_communities_h2',
        ];

        // Show/hide toggle for the Other Cities section (checkbox, default 1 = shown).
        update_post_meta(
            $post_id,
            '_edp_show_other_cities',
            isset($_POST['edp_show_other_cities']) ? 1 : 0
        );

        foreach ($text_fields as $meta_key => $post_key) {
            $value = isset($_POST[$post_key])
                ? sanitize_text_field(wp_unslash((string) $_POST[$post_key]))
                : '';
            update_post_meta($post_id, $meta_key, $value);
        }

        $textarea_fields = [
            '_edp_meta_description' => 'edp_meta_description',
            '_edp_communities_body' => 'edp_communities_body',
        ];

        foreach ($textarea_fields as $meta_key => $post_key) {
            $value = isset($_POST[$post_key])
                ? wp_kses_post(wp_unslash((string) $_POST[$post_key]))
                : '';
            update_post_meta($post_id, $meta_key, $value);
        }

        // Sync WP editor (post_content) → _edp_body so the content resolver finds it.
        if (isset($_POST['content'])) {
            update_post_meta($post_id, '_edp_body', wp_kses_post(wp_unslash((string) $_POST['content'])));
        }
    }

    /** Injects list/typography styles into the TinyMCE content iframe. */
    public static function editor_content_style(array $settings): array
    {
        global $post;

        if (!isset($post) || $post->post_type !== self::POST_TYPE) {
            return $settings;
        }

        $css = 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.65;color:#3a3541;margin:10px 14px}'
            . 'ol,ul{padding-left:1.6em;margin:0.5em 0}'
            . 'ol{list-style:decimal}'
            . 'ul{list-style:disc}'
            . 'li{margin-bottom:0.3em}'
            . 'p{margin:0 0 0.8em}'
            . 'a{color:#6e39cb}';

        $settings['content_style'] = ($settings['content_style'] ?? '') . ' ' . $css;

        return $settings;
    }

    public static function disable_block_editor(bool $use, string $type): bool
    {
        return $type === self::POST_TYPE ? false : $use;
    }

    /** Hides featured image, slug, and title metaboxes for this CPT. */
    public static function hide_meta_boxes_css(): void
    {
        global $post;

        if (!isset($post) || $post->post_type !== self::POST_TYPE) {
            return;
        }

        echo '<style>'
            . '#postimagediv,#slugdiv,#titlediv{display:none!important}'
            . '#postbox-container-1{display:none!important}'
            . '#postbox-container-2{width:100%!important;float:none!important}'
            . '#post-body-content{margin-right:0!important}'
            . '</style>' . "\n";
    }
}
