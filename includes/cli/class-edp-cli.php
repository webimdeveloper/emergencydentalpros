<?php
/**
 * WP-CLI commands.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Import locations from a CSV file.
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Absolute path to CSV. Defaults to plugin raw_data.csv.
     *
     * ## EXAMPLES
     *
     *     wp edp-seo import
     *     wp edp-seo import /path/to/raw_data.csv
     *
     * @param list<string> $args Positional args.
     */
    $edp_cli_import = static function (array $args): void {
        $path = isset($args[0]) && is_string($args[0]) && $args[0] !== ''
            ? $args[0]
            : EDP_PLUGIN_DIR . 'raw_data.csv';

        if (!is_readable($path)) {
            WP_CLI::error('CSV not readable: ' . $path);
        }

        $result = EDP_Importer::import_from_csv_file($path);

        if (!empty($result['error'])) {
            WP_CLI::error(
                sprintf(
                    'Import failed (%s). Path: %s',
                    (string) $result['error'],
                    (string) ($result['path'] ?? $path)
                )
            );
        }

        WP_CLI::success(
            sprintf(
                'Rows: %d — Skipped: %d — Groups: %d',
                (int) ($result['rows'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['groups'] ?? 0)
            )
        );
    };

    WP_CLI::add_command('edp-seo import', $edp_cli_import);

    /**
     * Import Yelp dentist listings for a batch of cities.
     *
     * ## OPTIONS
     *
     * [--offset=<n>]
     * : Offset into the locations table (by id order). Default: 0
     *
     * [--limit=<n>]
     * : Number of cities to process. Default: 50
     *
     * [--search-only]
     * : Only run Business Search (no per-business Details). Faster and fewer API calls; hours will be empty.
     *
     * ## EXAMPLES
     *
     *     wp edp-seo import-yelp --offset=0 --limit=100
     *     wp edp-seo import-yelp --offset=100 --limit=100 --search-only
     *
     * @param list<string> $args Positional args.
     * @param array<string, mixed> $assoc_args Associative args.
     */
    $edp_cli_yelp = static function (array $args, array $assoc_args): void {
        $offset = isset($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0;
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 50;
        $offset = max(0, $offset);
        $limit = max(1, min(500, $limit));

        $fetch_details = !isset($assoc_args['search-only']);

        $result = EDP_Yelp_Importer::import_batch($offset, $limit, $fetch_details);

        if (empty($result['ok'])) {
            WP_CLI::error(
                isset($result['error'])
                    ? 'Yelp import failed: ' . (string) $result['error']
                    : 'Yelp import failed.'
            );
        }

        WP_CLI::success(
            sprintf(
                'Cities processed: %d — API calls (approx.): %d',
                (int) ($result['processed'] ?? 0),
                (int) ($result['api_calls'] ?? 0)
            )
        );

        if (!empty($result['messages']) && is_array($result['messages'])) {
            foreach ($result['messages'] as $msg) {
                WP_CLI::warning((string) $msg);
            }
        }
    };

    WP_CLI::add_command('edp-seo import-yelp', $edp_cli_yelp);

    /**
     * Run one Yelp Business Search to verify API key (does not write to DB).
     *
     * ## EXAMPLES
     *
     *     wp edp-seo test-yelp
     */
    $edp_cli_test_yelp = static function (): void {
        $result = EDP_Yelp_Importer::test_api_connection();

        if (empty($result['ok'])) {
            WP_CLI::error((string) ($result['message'] ?? 'Test failed.'));
        }

        WP_CLI::success((string) ($result['message'] ?? 'OK'));

        if (isset($result['api_calls'])) {
            WP_CLI::log('API calls: ' . (int) $result['api_calls']);
        }
    };

    WP_CLI::add_command('edp-seo test-yelp', $edp_cli_test_yelp);
}
