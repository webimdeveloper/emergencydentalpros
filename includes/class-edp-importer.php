<?php
/**
 * CSV import: aggregate by city/state, USA 50+DC filter.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Importer
{
    // -------------------------------------------------------------------------
    // Public entry points
    // -------------------------------------------------------------------------

    /**
     * Import from a CSV file on disk (no delete — upsert only).
     * Kept for backward compatibility with WP-CLI and manual uploads.
     *
     * @return array{rows:int, skipped:int, groups:int, error?:string, path?:string}
     */
    public static function import_from_csv_file(string $absolute_path): array
    {
        $empty = ['rows' => 0, 'skipped' => 0, 'groups' => 0];

        if ($absolute_path === '' || !is_readable($absolute_path)) {
            return array_merge($empty, [
                'error' => 'file_not_readable',
                'path'  => $absolute_path,
            ]);
        }

        EDP_Database::ensure_schema();

        $handle = fopen($absolute_path, 'rb');

        if ($handle === false) {
            return array_merge($empty, [
                'error' => 'fopen_failed',
                'path'  => $absolute_path,
            ]);
        }

        $parsed = self::parse_csv_handle($handle);
        fclose($handle);

        if (isset($parsed['error'])) {
            return array_merge($empty, [
                'error' => $parsed['error'],
                'path'  => $absolute_path,
            ]);
        }

        $groups = $parsed['groups'];

        foreach ($groups as $group) {
            $row = self::build_row($group);
            $ok  = self::upsert_row($row);

            if (!$ok) {
                global $wpdb;

                return [
                    'error'    => 'db_write_failed',
                    'db_error' => (string) $wpdb->last_error,
                    'rows'     => $parsed['rows'],
                    'skipped'  => $parsed['skipped'],
                    'groups'   => count($groups),
                    'path'     => $absolute_path,
                ];
            }
        }

        return [
            'rows'    => $parsed['rows'],
            'skipped' => $parsed['skipped'],
            'groups'  => count($groups),
            'path'    => $absolute_path,
        ];
    }

    /**
     * Full smart sync from a Google Sheets URL.
     *
     * - Fetches CSV from Google Sheets (no API key needed — sheet must be public).
     * - Upserts all cities from the sheet.
     * - For cities with a custom override (Redirect / Customize), city_name is
     *   locked — only zips and county data are refreshed.
     * - Cities that no longer appear in the sheet are deleted, UNLESS they have
     *   a custom override, in which case they are kept.
     *
     * @return array{
     *   rows: int,
     *   skipped: int,
     *   groups: int,
     *   upserted: int,
     *   safe_updated: int,
     *   removed: int,
     *   kept_protected: int,
     *   error?: string,
     *   source: string,
     * }
     */
    public static function sync_from_sheet(string $url): array
    {
        $empty = [
            'rows'           => 0,
            'skipped'        => 0,
            'groups'         => 0,
            'upserted'       => 0,
            'safe_updated'   => 0,
            'removed'        => 0,
            'kept_protected' => 0,
            'source'         => 'sheet',
        ];

        if ($url === '') {
            return array_merge($empty, ['error' => 'no_url']);
        }

        EDP_Database::ensure_schema();

        // 1. Fetch CSV from Google Sheets.
        $csv = EDP_Sheet_Fetcher::fetch_csv($url);

        if (is_wp_error($csv)) {
            return array_merge($empty, ['error' => $csv->get_error_message()]);
        }

        // 2. Parse CSV into city groups.
        $parsed = self::parse_csv_string($csv);

        if (isset($parsed['error'])) {
            return array_merge($empty, ['error' => $parsed['error']]);
        }

        $groups = $parsed['groups'];

        if ($groups === []) {
            return array_merge($empty, [
                'rows'    => $parsed['rows'],
                'skipped' => $parsed['skipped'],
                'error'   => 'no_valid_groups',
            ]);
        }

        // 3. Load all existing DB rows to compute protection and deletions.
        $existing_rows = EDP_Database::get_all_rows_for_sync();

        /** @var array<string, array{id: int, custom_post_id: int}> $existing_by_slug */
        $existing_by_slug = [];

        foreach ($existing_rows as $er) {
            $existing_by_slug[$er['city_slug']] = $er;
        }

        // Build set of city_slugs coming in from the sheet.
        $incoming_slugs = [];

        foreach ($groups as $group) {
            $row                          = self::build_row($group);
            $incoming_slugs[$row['city_slug']] = true;
        }

        // 4. Upsert all groups — safe mode for protected rows.
        $upserted     = 0;
        $safe_updated = 0;

        foreach ($groups as $group) {
            $row       = self::build_row($group);
            $slug      = $row['city_slug'];
            $protected = isset($existing_by_slug[$slug])
                && (int) $existing_by_slug[$slug]['custom_post_id'] > 0;

            if ($protected) {
                $ok = self::upsert_row_safe($row);
                $safe_updated++;
            } else {
                $ok = self::upsert_row($row);
            }

            if ($ok) {
                $upserted++;
            }
        }

        // 5. Delete DB rows not in the incoming set, protect rows with overrides.
        $removed        = 0;
        $kept_protected = 0;
        $ids_to_delete  = [];

        foreach ($existing_by_slug as $slug => $er) {
            if (isset($incoming_slugs[$slug])) {
                continue; // Still in sheet — skip.
            }

            if ((int) $er['custom_post_id'] > 0) {
                $kept_protected++;
            } else {
                $ids_to_delete[] = (int) $er['id'];
            }
        }

        if ($ids_to_delete !== []) {
            global $wpdb;
            $table = EDP_Database::table_name();

            foreach (array_chunk($ids_to_delete, 100) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $sql = $wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$chunk);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($sql);
                $removed += (int) $wpdb->rows_affected;
            }
        }

        return [
            'rows'           => $parsed['rows'],
            'skipped'        => $parsed['skipped'],
            'groups'         => count($groups),
            'upserted'       => $upserted,
            'safe_updated'   => $safe_updated,
            'removed'        => $removed,
            'kept_protected' => $kept_protected,
            'source'         => 'sheet:' . $url,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal: parsing
    // -------------------------------------------------------------------------

    /**
     * Parse an open CSV file handle into city groups.
     * The handle is NOT closed by this method — caller is responsible.
     *
     * @param resource $handle Open, readable file handle positioned at the start.
     * @return array{groups: array<string, array<string, mixed>>, rows: int, skipped: int, error?: string}
     */
    private static function parse_csv_handle($handle): array
    {
        $empty = ['groups' => [], 'rows' => 0, 'skipped' => 0];

        $header = fgetcsv($handle);

        if ($header === false) {
            return array_merge($empty, ['error' => 'empty_or_invalid_csv']);
        }

        $map = array_flip(array_map('strtolower', $header));

        $required = ['zip', 'city', 'state_id', 'state_name'];

        foreach ($required as $col) {
            if (!isset($map[$col])) {
                return array_merge($empty, ['error' => 'missing_columns']);
            }
        }

        $allowed = self::usa_state_ids();
        /** @var array<string, array<string, mixed>> $groups */
        $groups  = [];
        $rows    = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle)) !== false) {
            ++$rows;

            $zip_raw    = $data[$map['zip']] ?? '';
            $city       = isset($data[$map['city']]) ? (string) $data[$map['city']] : '';
            $state_id   = strtoupper(trim((string) ($data[$map['state_id']] ?? '')));
            $state_name = (string) ($data[$map['state_name']] ?? '');

            if ($state_id === '' || !isset($allowed[$state_id])) {
                ++$skipped;
                continue;
            }

            $zip = self::normalize_zip((string) $zip_raw);

            if ($zip === null) {
                ++$skipped;
                continue;
            }

            $city_trim = trim($city);

            if ($city_trim === '') {
                ++$skipped;
                continue;
            }

            $city_key = strtolower($city_trim);
            $gkey     = $state_id . '|' . $city_key;

            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'state_id'         => $state_id,
                    'state_name'       => $state_name !== '' ? $state_name : $state_id,
                    'city_name'        => $city_trim,
                    'zips'             => [],
                    'county_fips'      => isset($map['county_fips']) ? trim((string) ($data[$map['county_fips']] ?? '')) : '',
                    'county_name'      => isset($map['county_name']) ? trim((string) ($data[$map['county_name']] ?? '')) : '',
                    'county_names_all' => isset($map['county_names_all']) ? trim((string) ($data[$map['county_names_all']] ?? '')) : '',
                    'county_fips_all'  => isset($map['county_fips_all']) ? trim((string) ($data[$map['county_fips_all']] ?? '')) : '',
                ];
            }

            $groups[$gkey]['zips'][$zip] = true;
        }

        return [
            'groups'  => $groups,
            'rows'    => $rows,
            'skipped' => $skipped,
        ];
    }

    /**
     * Parse a CSV string into city groups using an in-memory stream.
     *
     * @return array{groups: array<string, array<string, mixed>>, rows: int, skipped: int, error?: string}
     */
    private static function parse_csv_string(string $csv): array
    {
        $empty = ['groups' => [], 'rows' => 0, 'skipped' => 0];

        $handle = fopen('php://memory', 'r+b');

        if ($handle === false) {
            return array_merge($empty, ['error' => 'stream_failed']);
        }

        fwrite($handle, $csv);
        rewind($handle);

        $result = self::parse_csv_handle($handle);
        fclose($handle);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Internal: DB writes
    // -------------------------------------------------------------------------

    /**
     * Build a DB row array from a parsed group.
     *
     * @param array<string, mixed> $group
     * @return array<string, string>
     */
    private static function build_row(array $group): array
    {
        $state_id   = (string) $group['state_id'];
        $state_name = (string) $group['state_name'];
        $city_name  = (string) $group['city_name'];
        $state_slug = sanitize_title($state_name);
        $city_part  = sanitize_title($city_name);
        $city_slug  = $city_part . '-' . strtolower($state_id);

        $zips = array_keys((array) $group['zips']);
        sort($zips);

        return [
            'state_slug'       => $state_slug,
            'state_name'       => $state_name,
            'state_id'         => $state_id,
            'city_slug'        => $city_slug,
            'city_name'        => $city_name,
            'zips'             => (string) wp_json_encode($zips),
            'county_fips'      => (string) ($group['county_fips'] ?? ''),
            'county_name'      => (string) ($group['county_name'] ?? ''),
            'county_names_all' => (string) ($group['county_names_all'] ?? ''),
            'county_fips_all'  => (string) ($group['county_fips_all'] ?? ''),
        ];
    }

    /**
     * Full upsert — updates all fields including city_name.
     * Used for rows without a custom override.
     *
     * @param array<string, string> $row
     */
    private static function upsert_row(array $row): bool
    {
        global $wpdb;

        $table      = EDP_Database::table_name();
        $updated_at = current_time('mysql');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
            (state_slug, state_name, state_id, city_slug, city_name, zips,
             county_fips, county_name, county_names_all, county_fips_all, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
            state_slug        = VALUES(state_slug),
            state_name        = VALUES(state_name),
            city_name         = VALUES(city_name),
            zips              = VALUES(zips),
            county_fips       = VALUES(county_fips),
            county_name       = VALUES(county_name),
            county_names_all  = VALUES(county_names_all),
            county_fips_all   = VALUES(county_fips_all),
            updated_at        = VALUES(updated_at)",
            $row['state_slug'],
            $row['state_name'],
            $row['state_id'],
            $row['city_slug'],
            $row['city_name'],
            $row['zips'],
            $row['county_fips'],
            $row['county_name'],
            $row['county_names_all'],
            $row['county_fips_all'],
            $updated_at
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);

        return $wpdb->last_error === '';
    }

    /**
     * Safe upsert — keeps city_name and city_slug unchanged for existing rows.
     * Only refreshes zips, state info, and county data.
     * Used for rows that have a custom override (Redirect / Customize).
     *
     * @param array<string, string> $row
     */
    private static function upsert_row_safe(array $row): bool
    {
        global $wpdb;

        $table      = EDP_Database::table_name();
        $updated_at = current_time('mysql');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
            (state_slug, state_name, state_id, city_slug, city_name, zips,
             county_fips, county_name, county_names_all, county_fips_all, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
            state_slug        = VALUES(state_slug),
            state_name        = VALUES(state_name),
            zips              = VALUES(zips),
            county_fips       = VALUES(county_fips),
            county_name       = VALUES(county_name),
            county_names_all  = VALUES(county_names_all),
            county_fips_all   = VALUES(county_fips_all),
            updated_at        = VALUES(updated_at)",
            $row['state_slug'],
            $row['state_name'],
            $row['state_id'],
            $row['city_slug'],
            $row['city_name'],
            $row['zips'],
            $row['county_fips'],
            $row['county_name'],
            $row['county_names_all'],
            $row['county_fips_all'],
            $updated_at
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);

        return $wpdb->last_error === '';
    }

    // -------------------------------------------------------------------------
    // Internal: helpers
    // -------------------------------------------------------------------------

    private static function normalize_zip(string $zip): ?string
    {
        $zip = preg_replace('/\D/', '', $zip);

        if ($zip === null || $zip === '') {
            return null;
        }

        if (strlen($zip) > 5) {
            $zip = substr($zip, 0, 5);
        }

        if (strlen($zip) < 1 || strlen($zip) > 5) {
            return null;
        }

        return str_pad($zip, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, true>
     */
    private static function usa_state_ids(): array
    {
        $ids = [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
            'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
            'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
            'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
            'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
            'DC', 'VI',
        ];

        return array_fill_keys($ids, true);
    }
}
