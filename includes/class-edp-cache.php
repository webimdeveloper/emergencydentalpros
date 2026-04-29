<?php
/**
 * Full-page HTML cache for city landing pages.
 *
 * Stores the rendered HTML for each /locations/{state}/{city}/ URL as a
 * WordPress transient (auto-upgraded to Redis/Memcached if an object cache
 * drop-in is installed). Logged-in users always bypass the cache so the WP
 * admin bar is never cached.
 *
 * Flow:
 *   template_redirect (priority 1)
 *     → transient hit  → echo HTML, exit
 *     → transient miss → ob_start(); page renders normally;
 *                        shutdown flushes buffer through our callback
 *                        which stores the output and returns it to the browser.
 *
 * @package EmergencyDentalPros
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EDP_Cache {

    const TRANSIENT_PREFIX = 'edp_pc_';

    /* ── Hooks ── */

    public static function register(): void {
        add_action( 'template_redirect',                   [ self::class, 'maybe_serve'      ], 1 );
        add_action( 'admin_post_edp_clear_page_cache',     [ self::class, 'handle_clear_all' ] );
        add_action( 'admin_post_edp_clear_page_cache_one', [ self::class, 'handle_clear_one' ] );
        // Auto-clear a page's cache when it is saved/updated in the WP editor.
        add_action( 'save_post', [ self::class, 'on_save_post' ], 10, 1 );
    }

    /* ── Serve or buffer ── */

    public static function maybe_serve(): void {
        if ( is_user_logged_in() )    return;
        if ( ! self::is_enabled() )   return;
        if ( ! self::is_cacheable() ) return;

        $key   = self::key_for_request();
        $entry = get_transient( $key );

        if ( is_array( $entry ) && isset( $entry['html'] ) ) {
            header( 'X-EDP-Cache: HIT' );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $entry['html'];
            exit;
        }

        /*
         * Buffer the page output and store it on shutdown.
         *
         * We run at shutdown priority 0 (before WP's wp_ob_end_flush_all at
         * priority 1). We record our exact buffer level so that in the shutdown
         * callback we can first flush any buffers WP opened during rendering
         * (they sit on top of ours) — their content flows into our buffer —
         * before we capture the whole page with ob_get_clean().
         */
        ob_start();
        $ttl       = self::ttl_seconds();
        $uri       = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        $our_level = ob_get_level();

        add_action( 'shutdown', static function () use ( $key, $ttl, $uri, $our_level ): void {
            $pre_level = ob_get_level();
            // Flush any buffers opened after ours so their content flows into ours.
            while ( ob_get_level() > $our_level ) {
                ob_end_flush();
            }
            $html    = ob_get_clean();
            $trimmed = $html === false ? '' : trim( $html );
            $len     = strlen( $trimmed );
            $debug   = "pre={$pre_level},our={$our_level},len={$len}";

            if ( $html === false || $len < 500 ) {
                if ( ! headers_sent() ) {
                    header( "X-EDP-Cache: SKIP,{$debug}" );
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo (string) $html;
                return;
            }
            set_transient( $key, [
                'url'  => strtok( $uri, '?' ),
                'time' => time(),
                'size' => strlen( $html ),
                'html' => $html,
            ], $ttl );
            if ( ! headers_sent() ) {
                header( "X-EDP-Cache: MISS,{$debug}" );
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;
        }, 0 );
    }

    /* ── Cache management ── */

    /** Delete every city-page cache entry. */
    public static function clear_all(): void {
        global $wpdb;
        $prefix = self::TRANSIENT_PREFIX;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s",
            '_transient_'         . $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        ) );
    }

    /** Delete the cache entry for a specific URL path (no query string). */
    public static function clear_path( string $path ): void {
        delete_transient( self::TRANSIENT_PREFIX . md5( $path ) );
    }

    /**
     * Return metadata for all cached pages.
     *
     * @return array<int, array{key:string, url:string, time:int, size:int}>
     */
    public static function get_cached_pages(): array {
        global $wpdb;
        $pattern = '_transient_' . self::TRANSIENT_PREFIX . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             ORDER BY option_name",
            $pattern
        ) );

        $pages = [];
        foreach ( $rows as $row ) {
            $entry = maybe_unserialize( $row->option_value );
            if ( ! is_array( $entry ) || empty( $entry['url'] ) ) {
                continue;
            }
            $pages[] = [
                'key'  => str_replace( '_transient_', '', $row->option_name ),
                'url'  => (string) ( $entry['url'] ?? '' ),
                'time' => (int)    ( $entry['time'] ?? 0 ),
                'size' => (int)    ( $entry['size'] ?? 0 ),
            ];
        }
        return $pages;
    }

    /**
     * Clear the cache for a post when it is saved in the WP editor.
     * Works for static city pages and any post type served at a
     * /locations/{state}/{city}/ permalink.
     */
    public static function on_save_post( int $post_id ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return;
        }

        $path = (string) parse_url( $permalink, PHP_URL_PATH );
        $path = '/' . trim( $path, '/' ) . '/';

        if ( preg_match( '#^/locations/[a-z0-9-]+/[a-z0-9-]+/$#', $path ) ) {
            self::clear_path( $path );
        }
    }

    /* ── Admin-post handlers ── */

    public static function handle_clear_all(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'emergencydentalpros' ), 403 );
        }
        check_admin_referer( 'edp_clear_page_cache' );
        self::clear_all();
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'edp-seo', 'cache_cleared' => '1' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public static function handle_clear_one(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'emergencydentalpros' ), 403 );
        }
        check_admin_referer( 'edp_clear_page_cache_one' );
        $path = isset( $_POST['cache_path'] )
            ? sanitize_text_field( wp_unslash( (string) $_POST['cache_path'] ) )
            : '';
        if ( $path !== '' ) {
            self::clear_path( $path );
        }
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'edp-seo', 'cache_cleared' => '1' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /* ── Private helpers ── */

    private static function is_enabled(): bool {
        $s = get_option( EDP_Settings::OPTION_KEY, [] );
        return ! empty( $s['page_cache']['enabled'] );
    }

    private static function ttl_seconds(): int {
        $s     = get_option( EDP_Settings::OPTION_KEY, [] );
        $hours = max( 1, (int) ( $s['page_cache']['ttl'] ?? 24 ) );
        return $hours * HOUR_IN_SECONDS;
    }

    private static function is_cacheable(): bool {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
            return false;
        }
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        /* Skip requests with query strings — they may carry user-specific params.
         * We check REQUEST_URI directly because WP's rewrite system always populates
         * $_SERVER['QUERY_STRING'] with internal vars even on clean pretty-permalink URLs. */
        if ( strpos( $uri, '?' ) !== false ) {
            return false;
        }
        return (bool) preg_match( '#^/locations/[a-z0-9-]+/[a-z0-9-]+/?$#', $uri );
    }

    private static function key_for_request(): string {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        return self::TRANSIENT_PREFIX . md5( strtok( $uri, '?' ) );
    }
}
