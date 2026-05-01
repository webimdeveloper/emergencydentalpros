<?php
/**
 * Virtual SEO routes: detect context, titles, meta, render plugin templates.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_View_Controller
{
    /** @var array<string, mixed>|null */
    private static $ctx = null;

    public static function register(): void
    {
        add_action('wp', [self::class, 'bootstrap'], 0);
        add_action('template_redirect', [self::class, 'redirect_mapped_posts'], 0);
        add_action('template_redirect', [self::class, 'render'], 1);
    }

    public static function bootstrap(): void
    {
        if (is_admin()) {
            return;
        }

        $view = (string) get_query_var(EDP_Rewrite::Q_VIEW);

        if ($view === '') {
            return;
        }

        self::$ctx = null;

        switch ($view) {
            case EDP_Rewrite::VIEW_STATES:
                self::$ctx = [
                    'view' => 'state-list',
                ];
                break;

            case EDP_Rewrite::VIEW_STATE:
                $state_slug = sanitize_title((string) get_query_var(EDP_Rewrite::Q_STATE));

                if ($state_slug === '' || !EDP_Database::state_exists($state_slug)) {
                    self::set_404();

                    return;
                }

                self::$ctx = [
                    'view' => 'state',
                    'state_slug' => $state_slug,
                ];
                break;

            case EDP_Rewrite::VIEW_CITY:
                $state_slug = sanitize_title((string) get_query_var(EDP_Rewrite::Q_STATE));
                $city_slug = sanitize_title((string) get_query_var(EDP_Rewrite::Q_CITY));

                if ($state_slug === '' || $city_slug === '') {
                    self::set_404();

                    return;
                }

                $row = EDP_Database::get_city_row($state_slug, $city_slug);

                if ($row === null) {
                    self::set_404();

                    return;
                }

                if (($row['page_status'] ?? 'published') === 'draft') {
                    self::set_404();

                    return;
                }

                self::$ctx = [
                    'view' => 'city',
                    'state_slug' => $state_slug,
                    'city_slug' => $city_slug,
                    'row' => $row,
                ];
                break;

            default:
                self::set_404();
        }

        if (self::$ctx === null) {
            return;
        }

        // Virtual routes have no matching post; core marks the main query as 404 unless we clear it.
        global $wp_query;
        $wp_query->is_404 = false;

        add_filter('pre_get_document_title', [self::class, 'filter_document_title'], 20);
        add_action('wp_head', [self::class, 'output_canonical'], 1);
        add_action('wp_head', [self::class, 'output_meta_description'], 1);
        add_action('wp_head', [self::class, 'output_og_tags'], 2);

        if ((self::$ctx['view'] ?? '') === 'city') {
            add_action('wp_head', [self::class, 'output_city_schema'], 99);
            add_action('wp_head', [self::class, 'output_faqpage_schema'], 99);
        }
    }

    private static function set_404(): void
    {
        global $wp_query;

        $wp_query->set_404();
        status_header(404);
    }

    public static function filter_document_title(string $title): string
    {
        if (self::$ctx === null) {
            return $title;
        }

        $settings = EDP_Settings::get_all();
        $templates = $settings['templates'] ?? [];
        $base = EDP_Template_Engine::base_vars();

        $view = (string) (self::$ctx['view'] ?? '');

        if ($view === 'state-list') {
            $t = $templates['states_index']['meta_title'] ?? '';

            return EDP_Template_Engine::replace((string) $t, $base);
        }

        if ($view === 'state') {
            $slug = (string) (self::$ctx['state_slug'] ?? '');
            $state_row = self::get_state_row_by_slug($slug);

            if ($state_row === null) {
                return $title;
            }

            $vars = EDP_Template_Engine::context_from_state($base, $state_row);
            $t = $templates['state_cities']['meta_title'] ?? '';

            return EDP_Template_Engine::replace((string) $t, $vars);
        }

        if ($view === 'city') {
            $row = self::$ctx['row'] ?? null;

            if (!is_array($row)) {
                return $title;
            }

            $resolved = EDP_Content_Resolver::resolve_city($row);

            return $resolved['title'];
        }

        return $title;
    }

    public static function output_meta_description(): void
    {
        if (self::$ctx === null) {
            return;
        }

        $settings = EDP_Settings::get_all();
        $templates = $settings['templates'] ?? [];
        $base = EDP_Template_Engine::base_vars();
        $view = (string) (self::$ctx['view'] ?? '');

        $desc = '';

        if ($view === 'state-list') {
            $desc = EDP_Template_Engine::replace((string) ($templates['states_index']['meta_description'] ?? ''), $base);
        } elseif ($view === 'state') {
            $slug = (string) (self::$ctx['state_slug'] ?? '');
            $state_row = self::get_state_row_by_slug($slug);

            if ($state_row !== null) {
                $vars = EDP_Template_Engine::context_from_state($base, $state_row);
                $desc = EDP_Template_Engine::replace((string) ($templates['state_cities']['meta_description'] ?? ''), $vars);
            }
        } elseif ($view === 'city') {
            $row = self::$ctx['row'] ?? null;

            if (is_array($row)) {
                $resolved = EDP_Content_Resolver::resolve_city($row);
                $desc = $resolved['meta_description'];
            }
        }

        if ($desc === '') {
            return;
        }

        echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
    }

    public static function output_canonical(): void
    {
        if (self::$ctx === null) {
            return;
        }

        $view = (string) (self::$ctx['view'] ?? '');

        if ($view === 'state-list') {
            $url = home_url(user_trailingslashit('locations'));
        } elseif ($view === 'state') {
            $slug = (string) (self::$ctx['state_slug'] ?? '');
            $url  = home_url(user_trailingslashit('locations/' . rawurlencode($slug)));
        } elseif ($view === 'city') {
            $row  = self::$ctx['row'] ?? [];
            $url  = home_url(user_trailingslashit(
                'locations/' . rawurlencode((string) ($row['state_slug'] ?? '')) . '/' . rawurlencode((string) ($row['city_slug'] ?? ''))
            ));
        } else {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
    }

    public static function output_og_tags(): void
    {
        if (self::$ctx === null) {
            return;
        }

        $settings     = EDP_Settings::get_all();
        $og_image     = (string) ($settings['og_image_url'] ?? '');
        $twitter_site = (string) ($settings['twitter_site'] ?? '');
        $view         = (string) (self::$ctx['view'] ?? '');

        // Resolve title + description from the already-registered filters.
        $title = apply_filters('pre_get_document_title', '');
        $desc  = '';

        $templates = $settings['templates'] ?? [];
        $base      = EDP_Template_Engine::base_vars();

        if ($view === 'state-list') {
            $desc = EDP_Template_Engine::replace((string) ($templates['states_index']['meta_description'] ?? ''), $base);
        } elseif ($view === 'state') {
            $slug      = (string) (self::$ctx['state_slug'] ?? '');
            $state_row = self::get_state_row_by_slug($slug);
            if ($state_row !== null) {
                $vars = EDP_Template_Engine::context_from_state($base, $state_row);
                $desc = EDP_Template_Engine::replace((string) ($templates['state_cities']['meta_description'] ?? ''), $vars);
            }
        } elseif ($view === 'city') {
            $row = self::$ctx['row'] ?? null;
            if (is_array($row)) {
                $resolved = EDP_Content_Resolver::resolve_city($row);
                $desc = $resolved['meta_description'];
            }
        }

        $current_url = '';
        if ($view === 'state-list') {
            $current_url = home_url(user_trailingslashit('locations'));
        } elseif ($view === 'state') {
            $slug        = (string) (self::$ctx['state_slug'] ?? '');
            $current_url = home_url(user_trailingslashit('locations/' . rawurlencode($slug)));
        } elseif ($view === 'city') {
            $row         = self::$ctx['row'] ?? [];
            $current_url = home_url(user_trailingslashit(
                'locations/' . rawurlencode((string) ($row['state_slug'] ?? '')) . '/' . rawurlencode((string) ($row['city_slug'] ?? ''))
            ));
        }

        echo '<meta property="og:type" content="website" />' . "\n";

        if ($title !== '') {
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        }

        if ($desc !== '') {
            echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        }

        if ($current_url !== '') {
            echo '<meta property="og:url" content="' . esc_url($current_url) . '" />' . "\n";
        }

        if ($og_image !== '') {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }

        if ($twitter_site !== '' || $og_image !== '') {
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        }

        if ($twitter_site !== '') {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '" />' . "\n";
        }
    }

    public static function output_faqpage_schema(): void
    {
        if (self::$ctx === null) {
            return;
        }

        $row = self::$ctx['row'] ?? null;

        if (!is_array($row)) {
            return;
        }

        $resolved  = EDP_Content_Resolver::resolve_city($row);
        $faq       = $resolved['faq'] ?? [];
        $faq_items = is_array($faq['items'] ?? null) ? $faq['items'] : [];

        if (empty($faq['enabled']) || empty($faq_items)) {
            return;
        }

        EDP_Schema::output_faqpage_schema($faq_items);
    }

    public static function output_city_schema(): void
    {
        if (self::$ctx === null) {
            return;
        }

        $row = self::$ctx['row'] ?? null;

        if (!is_array($row)) {
            return;
        }

        $resolved = EDP_Content_Resolver::resolve_city($row);
        EDP_Schema::output_city_schema($row, $resolved['title']);
    }

    /**
     * 301-redirect a mapped post's permalink to the plugin's canonical location URL.
     *
     * When an admin maps a post to a location via the "Redirect" action, visiting the
     * post's own permalink (e.g. /dallas-dentist/) should 301 to the plugin URL
     * (e.g. /locations/texas/dallas/) to prevent duplicate content.
     */
    public static function redirect_mapped_posts(): void
    {
        if (is_admin()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $post_id = (int) get_the_ID();

        if ($post_id <= 0) {
            return;
        }

        $location = EDP_Database::get_location_by_post_id($post_id);

        // Also check if this post is a redirect source stored on a CPT (coexistent scenario).
        if ($location === null) {
            $cpt_ids = get_posts([
                'post_type'      => EDP_CPT::POST_TYPE,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [[
                    'key'   => '_edp_redirect_post_id',
                    'value' => $post_id,
                    'type'  => 'NUMERIC',
                ]],
            ]);
            if (!empty($cpt_ids)) {
                $location_id = (int) get_post_meta($cpt_ids[0], '_edp_location_id', true);
                if ($location_id > 0) {
                    $location = EDP_Database::get_row_by_id($location_id);
                }
            }
        }

        if ($location === null) {
            return;
        }

        $state_slug = sanitize_title((string) ($location['state_slug'] ?? ''));
        $city_slug  = sanitize_title((string) ($location['city_slug'] ?? ''));

        if ($state_slug === '' || $city_slug === '') {
            return;
        }

        $url = home_url(user_trailingslashit(
            'locations/' . rawurlencode($state_slug) . '/' . rawurlencode($city_slug)
        ));

        wp_redirect($url, 301);
        exit;
    }

    public static function render(): void
    {
        if (self::$ctx === null) {
            return;
        }

        if (is_404()) {
            return;
        }

        status_header(200);
        nocache_headers();

        $edp_view = (string) (self::$ctx['view'] ?? '');
        $edp_data = self::build_view_data();

        $shell = EDP_PLUGIN_DIR . 'templates/shell.php';

        if (is_readable($shell)) {
            include $shell;
        }

        exit;
    }

    /**
     * Resolve a view template — theme override takes priority over plugin default.
     * Filterable via 'edp_template' for advanced overrides.
     */
    public static function resolve_template(string $view_name): string
    {
        $theme_tpl = get_stylesheet_directory()
            . '/emergencydentalpros/views/'
            . $view_name . '.php';

        $plugin_tpl = EDP_PLUGIN_DIR . 'templates/views/' . $view_name . '.php';

        $resolved = file_exists($theme_tpl) ? $theme_tpl : $plugin_tpl;

        return (string) apply_filters('edp_template', $resolved, $view_name);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_view_data(): array
    {
        $settings = EDP_Settings::get_all();
        $templates = $settings['templates'] ?? [];
        $base = EDP_Template_Engine::base_vars();
        $view = (string) (self::$ctx['view'] ?? '');

        if ($view === 'state-list') {
            $t = $templates['states_index'] ?? [];
            $h1       = EDP_Template_Engine::replace((string) ($t['h1'] ?? ''), $base);
            $subtitle = EDP_Template_Engine::replace((string) ($t['subtitle'] ?? ''), $base);
            $body     = wpautop(EDP_Template_Engine::replace((string) ($t['body'] ?? ''), $base));

            $cities_by_state = EDP_Database::get_all_cities_grouped_by_state();
            $total_cities = array_sum(array_map('count', $cities_by_state));

            return [
                'h1'              => $h1,
                'subtitle'        => $subtitle,
                'body'            => $body,
                'states'          => EDP_Database::get_distinct_states(),
                'cities_by_state' => $cities_by_state,
                'total_cities'    => $total_cities,
            ];
        }

        if ($view === 'state') {
            $slug = (string) (self::$ctx['state_slug'] ?? '');
            $state_row = self::get_state_row_by_slug($slug);

            if ($state_row === null) {
                return ['h1' => '', 'body' => '', 'cities' => []];
            }

            $vars = EDP_Template_Engine::context_from_state($base, $state_row);
            $t = $templates['state_cities'] ?? [];
            $h1       = EDP_Template_Engine::replace((string) ($t['h1'] ?? ''), $vars);
            $subtitle = EDP_Template_Engine::replace((string) ($t['subtitle'] ?? ''), $vars);
            $body     = wpautop(EDP_Template_Engine::replace((string) ($t['body'] ?? ''), $vars));

            return [
                'h1'       => $h1,
                'subtitle' => $subtitle,
                'body'     => $body,
                'state'    => $state_row,
                'cities'   => EDP_Database::get_cities_by_state_slug($slug),
            ];
        }

        if ($view === 'city') {
            $row = self::$ctx['row'] ?? null;

            if (!is_array($row)) {
                return ['h1' => '', 'body' => '', 'zips' => [], 'nearby_businesses' => []];
            }

            $resolved = EDP_Content_Resolver::resolve_city($row);
            $vars = EDP_Template_Engine::context_from_city_row($base, $row);
            $zips = [];

            if (!empty($row['zips'])) {
                $decoded = json_decode((string) $row['zips'], true);

                if (is_array($decoded)) {
                    $zips = array_map('strval', $decoded);
                }
            }

            sort($zips);

            $location_id = (int) ($row['id'] ?? 0);
            $nearby = $location_id > 0
                ? EDP_Database::get_nearby_for_location($location_id, 'google')
                : [];

            $current_city_slug = (string) ($row['city_slug'] ?? '');
            $state_slug_for_cities = (string) ($row['state_slug'] ?? '');
            $all_state_cities = $state_slug_for_cities !== ''
                ? EDP_Database::get_cities_by_state_slug($state_slug_for_cities)
                : [];
            $other_cities = array_values(array_filter(
                $all_state_cities,
                fn($c) => sanitize_title((string) ($c['city_slug'] ?? '')) !== $current_city_slug
            ));
            if (count($other_cities) > 12) {
                $other_cities = array_slice($other_cities, 0, 12);
            }

            return [
                'h1'                => $resolved['h1'],
                'subtitle'          => $resolved['subtitle'] ?? '',
                'body'              => $resolved['html'],
                'zips'              => $zips,
                'row'               => $row,
                'source'            => $resolved['source'],
                'nearby_businesses' => $nearby,
                'other_cities'      => $other_cities,
                'communities_h2'    => $resolved['communities_h2'],
                'communities_body'  => $resolved['communities_body'],
                'other_cities_h2'   => $resolved['other_cities_h2'],
                'show_other_cities' => $resolved['show_other_cities'] ?? true,
                'faq'               => $resolved['faq'] ?? ['enabled' => false, 'h2' => '', 'intro' => '', 'items' => []],
            ];
        }

        return [];
    }

    /**
     * @return array<string, string>|null
     */
    private static function get_state_row_by_slug(string $state_slug): ?array
    {
        $states = EDP_Database::get_distinct_states();

        foreach ($states as $s) {
            if (isset($s['state_slug']) && sanitize_title((string) $s['state_slug']) === $state_slug) {
                return [
                    'state_slug' => (string) $s['state_slug'],
                    'state_name' => (string) ($s['state_name'] ?? ''),
                    'state_id' => (string) ($s['state_id'] ?? ''),
                ];
            }
        }

        return null;
    }
}
