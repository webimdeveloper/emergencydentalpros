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
    public const Q_VIEW = 'edp_seo_view';
    public const Q_STATE = 'edp_state_slug';
    public const Q_CITY = 'edp_city_slug';

    public const VIEW_STATES = 'states';
    public const VIEW_STATE = 'state';
    public const VIEW_CITY = 'city';

    public const OPTION_REWRITE_VERSION = 'edp_seo_rewrite_rules_version';

    public static function register(): void
    {
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('init', [self::class, 'add_rules'], 5);
        add_filter('request', [self::class, 'backfill_request'], 0);
        add_action('init', [self::class, 'maybe_flush_rewrite_rules'], 99);
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

        if (preg_match('#^locations/?$#', $rel)) {
            self::strip_conflicting_query_vars($query_vars);
            $query_vars[self::Q_VIEW] = self::VIEW_STATES;

            return $query_vars;
        }

        if (preg_match('#^locations/([^/]+)/?$#', $rel, $m)) {
            self::strip_conflicting_query_vars($query_vars);
            $query_vars[self::Q_VIEW] = self::VIEW_STATE;
            $query_vars[self::Q_STATE] = $m[1];

            return $query_vars;
        }

        if (preg_match('#^locations/([^/]+)/([^/]+)/?$#', $rel, $m)) {
            self::strip_conflicting_query_vars($query_vars);
            $query_vars[self::Q_VIEW] = self::VIEW_CITY;
            $query_vars[self::Q_STATE] = $m[1];
            $query_vars[self::Q_CITY] = $m[2];

            return $query_vars;
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

        return $vars;
    }

    public static function add_rules(): void
    {
        $base = 'locations';

        add_rewrite_rule(
            '^' . $base . '/?$',
            'index.php?' . self::Q_VIEW . '=' . self::VIEW_STATES,
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/([^/]+)/?$',
            'index.php?' . self::Q_VIEW . '=' . self::VIEW_STATE . '&' . self::Q_STATE . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/([^/]+)/([^/]+)/?$',
            'index.php?' . self::Q_VIEW . '=' . self::VIEW_CITY . '&' . self::Q_STATE . '=$matches[1]&' . self::Q_CITY . '=$matches[2]',
            'top'
        );
    }
}
