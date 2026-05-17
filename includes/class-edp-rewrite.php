<?php
/**
 * Rewrite rules and query vars for virtual location URLs.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Rewrite
{
    public const Q_VIEW  = 'edp_seo_view';
    public const Q_STATE = 'edp_state_slug';
    public const Q_CITY  = 'edp_city_slug';
    public const Q_SLUG  = 'edp_slug';

    public const VIEW_STATES    = 'states';
    public const VIEW_STATE     = 'state';
    public const VIEW_CITY      = 'city';
    public const VIEW_CITY_FLAT = 'city_flat';
    public const VIEW_AUTO      = 'auto';

    public const OPTION_REWRITE_VERSION = 'edp_seo_rewrite_rules_version';

    public static function register(): void
    {
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('init', [self::class, 'add_rules'], 5);
        add_filter('request', [self::class, 'backfill_request'], 0);
        add_action('init', [self::class, 'maybe_flush_rewrite_rules'], 99);
        add_filter('pre_handle_404', [self::class, 'skip_404_for_sitemaps'], 0);
    }

    /**
     * Prevent WordPress handle_404() from marking sitemap requests as 404.
     * Apache's PATH_INFO mangling can strip .xml from REQUEST_URI so WP_Query
     * never sees the sitemap query var — this filter catches that gap.
     *
     * @param bool $preempt
     * @return bool
     */
    public static function skip_404_for_sitemaps(bool $preempt): bool
    {
        if ($preempt) {
            return $preempt;
        }

        $rel = self::get_request_path_relative_to_home();

        if ($rel === null) {
            return $preempt;
        }

        if (
            $rel === 'wp-sitemap.xml' ||
            $rel === 'wp-sitemap.xsl' ||
            $rel === 'wp-sitemap-index.xsl' ||
            preg_match('#^wp-sitemap-.+\.xml$#', $rel)
        ) {
            return true; // Skip 404 handling; WP_Sitemaps::render_sitemaps() will serve the response.
        }

        return $preempt;
    }

    /**
     * When pretty permalinks or rule cache are stale, core may not set our query vars.
     * Parse the request path and inject vars so /locations/... always resolves.
     *
     * @param array<string, string> $query_vars Main query vars.
     * @return array<string, string>
     */
    public static function backfill_request(array $query_vars): array
    {
        if (is_admin()) {
            return $query_vars;
        }

        if (!empty($query_vars[self::Q_VIEW])) {
            return $query_vars;
        }

        $rel = self::get_request_path_relative_to_home();

        if ($rel === null) {
            return $query_vars;
        }

        // PATH_INFO mangling in Apache can strip .xml extensions from REQUEST_URI, causing
        // WP's parse_request to miss the built-in sitemap rewrite rules. Inject them here
        // so wp-sitemap.xml and sub-sitemaps always resolve correctly.
        if ($rel === 'wp-sitemap.xml') {
            $query_vars['sitemap'] = 'index';
            return $query_vars;
        }
        if (preg_match('#^wp-sitemap-([a-z]+?)-([a-z\d_-]+?)-(\d+?)\.xml$#', $rel, $m)) {
            $query_vars['sitemap']         = $m[1];
            $query_vars['sitemap-subtype'] = $m[2];
            $query_vars['paged']           = $m[3];
            return $query_vars;
        }
        if (preg_match('#^wp-sitemap-([a-z]+?)-(\d+?)\.xml$#', $rel, $m)) {
            $query_vars['sitemap'] = $m[1];
            $query_vars['paged']   = $m[2];
            return $query_vars;
        }

        $settings = EDP_Settings::get_all();
        $base     = sanitize_title($settings['url_base'] ?? 'locations') ?: 'locations';
        $mode     = $settings['url_mode'] ?? 'hierarchical';
        $base_re  = preg_quote($base, '#');

        if (preg_match('#^' . $base_re . '/?$#', $rel)) {
            self::strip_conflicting_query_vars($query_vars);
            $query_vars[self::Q_VIEW] = self::VIEW_STATES;

            return $query_vars;
        }

        if ($mode === 'flat') {
            // City: slug ends in -{2-letter-state-code}
            if (preg_match('#^([a-z0-9][a-z0-9-]*-[a-z]{2})/?$#', $rel, $m)) {
                self::strip_conflicting_query_vars($query_vars);
                $query_vars[self::Q_VIEW] = self::VIEW_CITY_FLAT;
                $query_vars[self::Q_SLUG] = $m[1];

                return $query_vars;
            }

            // State or unknown — view controller validates
            if (preg_match('#^([a-z][a-z0-9-]+)/?$#', $rel, $m)) {
                self::strip_conflicting_query_vars($query_vars);
                $query_vars[self::Q_VIEW] = self::VIEW_AUTO;
                $query_vars[self::Q_SLUG] = $m[1];

                return $query_vars;
            }
        } else {
            if (preg_match('#^' . $base_re . '/([^/]+)/?$#', $rel, $m)) {
                self::strip_conflicting_query_vars($query_vars);
                $query_vars[self::Q_VIEW] = self::VIEW_STATE;
                $query_vars[self::Q_STATE] = $m[1];

                return $query_vars;
            }

            if (preg_match('#^' . $base_re . '/([^/]+)/([^/]+)/?$#', $rel, $m)) {
                self::strip_conflicting_query_vars($query_vars);
                $query_vars[self::Q_VIEW] = self::VIEW_CITY;
                $query_vars[self::Q_STATE] = $m[1];
                $query_vars[self::Q_CITY] = $m[2];

                return $query_vars;
            }
        }

        return $query_vars;
    }

    /**
     * Request path below the site home, no leading/trailing slashes (except empty).
     */
    public static function get_request_path_relative_to_home(): ?string
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return null;
        }

        $raw = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
        $path = parse_url($raw, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        $path = trim($path, '/');

        $home_path = parse_url(home_url('/'), PHP_URL_PATH);
        if (is_string($home_path)) {
            $home_path = trim($home_path, '/');
        } else {
            $home_path = '';
        }

        if ($home_path !== '') {
            if ($path === $home_path) {
                $path = '';
            } elseif (strpos($path, $home_path . '/') === 0) {
                $path = substr($path, strlen($home_path) + 1);
            }
        }

        return $path;
    }

    /**
     * @param array<string, string> $query_vars
     */
    private static function strip_conflicting_query_vars(array &$query_vars): void
    {
        foreach (['pagename', 'name', 'attachment', 'attachment_id', 'error', 'preview', 'page_id', 'p'] as $key) {
            unset($query_vars[$key]);
        }
    }

    /**
     * Flush rewrite rules once per plugin version (after deploy or code update).
     */
    public static function maybe_flush_rewrite_rules(): void
    {
        $stored = (string) get_option(self::OPTION_REWRITE_VERSION, '');
        $current = defined('EDP_PLUGIN_VERSION') ? (string) EDP_PLUGIN_VERSION : '0';

        if ($stored === $current) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE_VERSION, $current, false);
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public static function query_vars(array $vars): array
    {
        $vars[] = self::Q_VIEW;
        $vars[] = self::Q_STATE;
        $vars[] = self::Q_CITY;
        $vars[] = self::Q_SLUG;

        // Ensure WP core sitemap query vars survive alongside our custom vars.
        $vars[] = 'sitemap';
        $vars[] = 'sitemap-subtype';

        return $vars;
    }

    public static function add_rules(): void
    {
        $settings = EDP_Settings::get_all();
        $base     = sanitize_title($settings['url_base'] ?? 'locations') ?: 'locations';
        $mode     = $settings['url_mode'] ?? 'hierarchical';

        // States index — same in both modes.
        add_rewrite_rule(
            '^' . preg_quote($base, '#') . '/?$',
            'index.php?' . self::Q_VIEW . '=' . self::VIEW_STATES,
            'top'
        );

        if ($mode === 'flat') {
            // City slugs always end in -{2-letter-state-code}, e.g. auburn-al, san-diego-ca.
            // This pattern fires before the generic state rule to avoid ambiguity.
            add_rewrite_rule(
                '^([a-z0-9][a-z0-9-]*-[a-z]{2})/?$',
                'index.php?' . self::Q_VIEW . '=' . self::VIEW_CITY_FLAT . '&' . self::Q_SLUG . '=$matches[1]',
                'top'
            );

            // State slugs — single-word or hyphenated, view controller validates against DB.
            add_rewrite_rule(
                '^([a-z][a-z0-9-]+)/?$',
                'index.php?' . self::Q_VIEW . '=' . self::VIEW_AUTO . '&' . self::Q_SLUG . '=$matches[1]',
                'top'
            );
        } else {
            add_rewrite_rule(
                '^' . preg_quote($base, '#') . '/([^/]+)/?$',
                'index.php?' . self::Q_VIEW . '=' . self::VIEW_STATE . '&' . self::Q_STATE . '=$matches[1]',
                'top'
            );

            add_rewrite_rule(
                '^' . preg_quote($base, '#') . '/([^/]+)/([^/]+)/?$',
                'index.php?' . self::Q_VIEW . '=' . self::VIEW_CITY . '&' . self::Q_STATE . '=$matches[1]&' . self::Q_CITY . '=$matches[2]',
                'top'
            );
        }
    }

    /**
     * Returns the active URL mode: 'flat' or 'hierarchical'.
     */
    public static function get_url_mode(): string
    {
        $settings = EDP_Settings::get_all();
        return ($settings['url_mode'] ?? 'hierarchical') === 'flat' ? 'flat' : 'hierarchical';
    }

    /**
     * Build the canonical URL for a city given its row.
     *
     * @param array<string, string> $row Location DB row.
     */
    public static function city_url(array $row): string
    {
        $base = sanitize_title(EDP_Settings::get_all()['url_base'] ?? 'locations') ?: 'locations';

        if (self::get_url_mode() === 'flat') {
            return home_url('/' . $row['city_slug'] . '/');
        }

        return home_url('/' . $base . '/' . $row['state_slug'] . '/' . $row['city_slug'] . '/');
    }

    /**
     * Build the canonical URL for a state.
     */
    public static function state_url(string $state_slug): string
    {
        $base = sanitize_title(EDP_Settings::get_all()['url_base'] ?? 'locations') ?: 'locations';

        if (self::get_url_mode() === 'flat') {
            return home_url('/' . $state_slug . '/');
        }

        return home_url('/' . $base . '/' . $state_slug . '/');
    }

    /**
     * Build the canonical URL for the states index.
     */
    public static function states_url(): string
    {
        $base = sanitize_title(EDP_Settings::get_all()['url_base'] ?? 'locations') ?: 'locations';
        return home_url('/' . $base . '/');
    }
}
