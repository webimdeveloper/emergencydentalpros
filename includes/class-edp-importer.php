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
    /**
     * @return array{rows:int, skipped:int, groups:int, error?:string, path?:string}
     */
    public static function import_from_csv_file(string $absolute_path): array
    {
        $empty = ['rows' => 0, 'skipped' => 0, 'groups' => 0];

        if ($absolute_path === '' || !is_readable($absolute_path)) {
            return array_merge($empty, [
                'error' => 'file_not_readable',
                'path' => $absolute_path,
            ]);
        }

        $handle = fopen($absolute_path, 'rb');

        if ($handle === false) {
            return array_merge($empty, [
                'error' => 'fopen_failed',
                'path' => $absolute_path,
            ]);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return array_merge($empty, [
                'error' => 'empty_or_invalid_csv',
                'path' => $absolute_path,
            ]);
        }

        $map = array_flip(array_map('strtolower', $header));

        $required = ['zip', 'city', 'state_id', 'state_name'];

        foreach ($required as $col) {
            if (!isset($map[$col])) {
                fclose($handle);

                return array_merge($empty, [
                    'error' => 'missing_columns',
                    'path' => $absolute_path,
                ]);
            }
        }

        $allowed = self::usa_state_ids();
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $rows = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle)) !== false) {
            ++$rows;

            $zip_raw = $data[$map['zip']] ?? '';
            $city = isset($data[$map['city']]) ? (string) $data[$map['city']] : '';
            $state_id = strtoupper(trim((string) ($data[$map['state_id']] ?? '')));
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
            $gkey = $state_id . '|' . $city_key;

            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'state_id' => $state_id,
                    'state_name' => $state_name !== '' ? $state_name : $state_id,
                    'city_name' => $city_trim,
                    'zips' => [],
                    'county_fips' => isset($map['county_fips']) ? trim((string) ($data[$map['county_fips']] ?? '')) : '',
                    'county_name' => isset($map['county_name']) ? trim((string) ($data[$map['county_name']] ?? '')) : '',
                    'county_names_all' => isset($map['county_names_all']) ? trim((string) ($data[$map['county_names_all']] ?? '')) : '',
                    'county_fips_all' => isset($map['county_fips_all']) ? trim((string) ($data[$map['county_fips_all']] ?? '')) : '',
                ];
            }

            $groups[$gkey]['zips'][$zip] = true;
        }

        fclose($handle);

        $inserted = 0;

        foreach ($groups as $group) {
            $state_id = (string) $group['state_id'];
            $state_name = (string) $group['state_name'];
            $city_name = (string) $group['city_name'];

            $state_slug = sanitize_title($state_name);
            $city_part = sanitize_title($city_name);
            $city_slug = $city_part . '-' . strtolower($state_id);

            $zips = array_keys($group['zips']);
            sort($zips);

            self::upsert_row(
                [
                    'state_slug' => $state_slug,
                    'state_name' => $state_name,
                    'state_id' => $state_id,
                    'city_slug' => $city_slug,
                    'city_name' => $city_name,
                    'zips' => wp_json_encode($zips),
                    'county_fips' => (string) $group['county_fips'],
                    'county_name' => (string) $group['county_name'],
                    'county_names_all' => (string) $group['county_names_all'],
                    'county_fips_all' => (string) $group['county_fips_all'],
                ]
            );
        }

        return [
            'rows' => $rows,
            'skipped' => $skipped,
            'groups' => count($groups),
            'path' => $absolute_path,
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private static function upsert_row(array $row): void
    {
        global $wpdb;

        $table = EDP_Database::table_name();

        $county_fips = $row['county_fips'] !== '' ? $row['county_fips'] : '';
        $county_name = $row['county_name'] !== '' ? $row['county_name'] : '';
        $county_names_all = $row['county_names_all'] !== '' ? $row['county_names_all'] : '';
        $county_fips_all = $row['county_fips_all'] !== '' ? $row['county_fips_all'] : '';
        $updated_at = current_time('mysql');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
            (state_slug, state_name, state_id, city_slug, city_name, zips, county_fips, county_name, county_names_all, county_fips_all, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
            state_slug = VALUES(state_slug),
            state_name = VALUES(state_name),
            city_name = VALUES(city_name),
            zips = VALUES(zips),
            county_fips = VALUES(county_fips),
            county_name = VALUES(county_name),
            county_names_all = VALUES(county_names_all),
            county_fips_all = VALUES(county_fips_all),
            updated_at = VALUES(updated_at)",
            $row['state_slug'],
            $row['state_name'],
            $row['state_id'],
            $row['city_slug'],
            $row['city_name'],
            $row['zips'],
            $county_fips,
            $county_name,
            $county_names_all,
            $county_fips_all,
            $updated_at
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);
    }

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
            'DC',
        ];

        return array_fill_keys($ids, true);
    }
}
