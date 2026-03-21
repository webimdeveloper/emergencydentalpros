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
}
