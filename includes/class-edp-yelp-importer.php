<?php
/**
 * One-time / batched Yelp import per city location row.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Yelp_Importer
{
    /**
     * Import Yelp listings for a batch of cities.
     *
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   processed: int,
     *   api_calls: int,
     *   messages?: list<string>
     * }
     */
    /**
     * One Business Search call to verify the API key (does not write to the database).
     *
     * @return array{ok: bool, message: string, api_calls: int, total?: int}
     */
    public static function test_api_connection(): array
    {
        $api_key = EDP_Yelp_Config::get_api_key();

        if ($api_key === '') {
            return [
                'ok' => false,
                'message' => __('No API key configured. Save your API key above (or define EDP_YELP_API_KEY in wp-config.php), then try again.', 'emergencydentalpros'),
                'api_calls' => 0,
            ];
        }

        $client = new EDP_Yelp_Client($api_key);
        $result = $client->business_search('Dentists', 'San Francisco, CA', 1);

        if (is_wp_error($result)) {
            return [
                'ok' => false,
                'message' => $result->get_error_message(),
                'api_calls' => 1,
            ];
        }

        $total = isset($result['total']) ? (int) $result['total'] : 0;
        $businesses = isset($result['businesses']) && is_array($result['businesses']) ? $result['businesses'] : [];
        $count = count($businesses);

        return [
            'ok' => true,
            'message' => sprintf(
                /* translators: 1: number of businesses in response, 2: total reported by Yelp */
                __('Connection OK. Sample search returned %1$d business(es); Yelp reports about %2$d total matches for this query.', 'emergencydentalpros'),
                $count,
                $total
            ),
            'total' => $total,
            'api_calls' => 1,
        ];
    }

    public static function import_batch(int $offset, int $limit_cities, ?bool $fetch_details = null): array
    {
        $locations = EDP_Database::get_locations_batch($offset, $limit_cities);

        return self::import_location_rows($locations, $fetch_details);
    }

    /**
     * Import Yelp for specific location row IDs (admin bulk / single row).
     *
     * @param list<int> $ids
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   processed: int,
     *   api_calls: int,
     *   messages?: list<string>
     * }
     */
    public static function import_for_location_ids(array $ids, ?bool $fetch_details = null): array
    {
        $rows = EDP_Database::get_locations_by_ids($ids);

        return self::import_location_rows($rows, $fetch_details);
    }

    /**
     * @param list<array{id:int, city_name:string, state_id:string}> $locations
     * @return array{ok: bool, error?: string, processed: int, api_calls: int, messages?: list<string>}
     */
    private static function import_location_rows(array $locations, ?bool $fetch_details): array
    {
        $api_key = EDP_Yelp_Config::get_api_key();

        if ($api_key === '') {
            return [
                'ok' => false,
                'error' => 'missing_api_key',
                'processed' => 0,
                'api_calls' => 0,
            ];
        }

        if ($fetch_details === null) {
            $fetch_details = EDP_Yelp_Config::should_fetch_details();
        }

        $term = EDP_Yelp_Config::get_term();
        $per_city = EDP_Yelp_Config::get_limit();
        $client = new EDP_Yelp_Client($api_key);

        $api_calls = 0;
        $messages = [];

        foreach ($locations as $loc) {
            $location_id = (int) ($loc['id'] ?? 0);

            if ($location_id <= 0) {
                continue;
            }

            $location_string = self::build_location_string($loc);

            if ($location_string === '') {
                $messages[] = sprintf('Skipped location ID %d (empty city/state).', $location_id);
                continue;
            }

            $search = $client->business_search($term, $location_string, $per_city);
            ++$api_calls;

            if (is_wp_error($search)) {
                $messages[] = sprintf(
                    'Location %d (%s): %s',
                    $location_id,
                    $location_string,
                    $search->get_error_message()
                );
                continue;
            }

            $businesses = isset($search['businesses']) && is_array($search['businesses'])
                ? $search['businesses']
                : [];

            EDP_Database::delete_nearby_for_location($location_id, 'yelp');

            $sort = 0;

            foreach ($businesses as $biz) {
                if (!is_array($biz)) {
                    continue;
                }

                $bid = isset($biz['id']) ? (string) $biz['id'] : '';

                if ($bid === '') {
                    continue;
                }

                $hours_text = '';

                if ($fetch_details) {
                    usleep(120000);
                    $details = $client->business_details($bid);
                    ++$api_calls;

                    if (!is_wp_error($details)) {
                        $hours_text = self::format_hours_from_details($details);
                    }

                    usleep(80000);
                }

                $phone = '';

                if (!empty($biz['display_phone'])) {
                    $phone = (string) $biz['display_phone'];
                } elseif (!empty($biz['phone'])) {
                    $phone = (string) $biz['phone'];
                }

                EDP_Database::insert_nearby_row(
                    [
                        'location_id' => $location_id,
                        'provider' => 'yelp',
                        'external_id' => $bid,
                        'sort_order' => $sort,
                        'name' => isset($biz['name']) ? (string) $biz['name'] : '',
                        'rating' => isset($biz['rating']) ? (float) $biz['rating'] : null,
                        'review_count' => isset($biz['review_count']) ? (int) $biz['review_count'] : null,
                        'phone' => $phone,
                        'image_url' => isset($biz['image_url']) ? (string) $biz['image_url'] : '',
                        'hours_text' => $hours_text,
                        'business_url' => isset($biz['url']) ? (string) $biz['url'] : '',
                        'fetched_at' => current_time('mysql'),
                    ]
                );

                ++$sort;

                if ($sort >= $per_city) {
                    break;
                }
            }

            usleep(150000);
        }

        return [
            'ok' => true,
            'processed' => count($locations),
            'api_calls' => $api_calls,
            'messages' => $messages,
        ];
    }

    /**
     * @param array{id:int, city_name:string, state_id:string} $loc
     */
    private static function build_location_string(array $loc): string
    {
        $city = trim((string) ($loc['city_name'] ?? ''));
        $state = strtoupper(trim((string) ($loc['state_id'] ?? '')));

        if ($city === '' || $state === '') {
            return '';
        }

        return $city . ', ' . $state;
    }

    /**
     * @param array<string, mixed> $details Business Details API payload
     */
    private static function format_hours_from_details(array $details): string
    {
        if (!empty($details['hours']) && is_array($details['hours'])) {
            foreach ($details['hours'] as $h) {
                if (is_array($h) && !empty($h['open']) && is_array($h['open'])) {
                    return self::format_open_blocks($h['open']);
                }
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $open
     */
    private static function format_open_blocks(array $open): string
    {
        $days = [
            __('Mon', 'emergencydentalpros'),
            __('Tue', 'emergencydentalpros'),
            __('Wed', 'emergencydentalpros'),
            __('Thu', 'emergencydentalpros'),
            __('Fri', 'emergencydentalpros'),
            __('Sat', 'emergencydentalpros'),
            __('Sun', 'emergencydentalpros'),
        ];

        $lines = [];

        foreach ($open as $o) {
            if (!is_array($o)) {
                continue;
            }

            $day = isset($o['day']) ? (int) $o['day'] : 0;

            if ($day < 0 || $day > 6) {
                continue;
            }

            $start = isset($o['start']) ? (string) $o['start'] : '';
            $end = isset($o['end']) ? (string) $o['end'] : '';
            $label = $days[$day] ?? (string) $day;

            $lines[] = $label . ': ' . self::format_hm($start) . ' – ' . self::format_hm($end);
        }

        return implode("\n", $lines);
    }

    private static function format_hm(string $hm): string
    {
        if (strlen($hm) !== 4 || !ctype_digit($hm)) {
            return $hm;
        }

        $dt = \DateTimeImmutable::createFromFormat('Hi', $hm);

        if ($dt === false) {
            return $hm;
        }

        return wp_date(get_option('time_format'), $dt->getTimestamp());
    }
}
