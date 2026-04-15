<?php
/**
 * One-time / batched Google Places import per city location row.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Google_Places_Importer
{
    /**
     * One Text Search to verify the API key (does not write to the database).
     *
     * @return array{ok: bool, message: string, api_calls: int, total?: int}
     */
    public static function test_api_connection(): array
    {
        $api_key = EDP_Google_Places_Config::get_api_key();

        if ($api_key === '') {
            return [
                'ok'        => false,
                'message'   => __('No API key configured. Save your API key above (or define EDP_GOOGLE_PLACES_API_KEY in wp-config.php), then try again.', 'emergencydentalpros'),
                'api_calls' => 0,
            ];
        }

        $client = new EDP_Google_Places_Client($api_key);
        $result = $client->text_search('emergency dentist San Francisco CA', 1);

        if (is_wp_error($result)) {
            return [
                'ok'        => false,
                'message'   => $result->get_error_message(),
                'api_calls' => 1,
            ];
        }

        $businesses = isset($result['results']) && is_array($result['results']) ? $result['results'] : [];
        $count = count($businesses);

        return [
            'ok'        => true,
            'message'   => sprintf(
                /* translators: %d: number of businesses in response */
                __('Connection OK. Sample search returned %d result(s).', 'emergencydentalpros'),
                $count
            ),
            'api_calls' => 1,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, processed: int, api_calls: int, messages?: list<string>}
     */
    public static function import_batch(int $offset, int $limit_cities, ?bool $fetch_details = null): array
    {
        $locations = EDP_Database::get_locations_batch($offset, $limit_cities);

        return self::import_location_rows($locations, $fetch_details);
    }

    /**
     * @param list<int> $ids
     * @return array{ok: bool, error?: string, processed: int, api_calls: int, messages?: list<string>}
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
        $api_key = EDP_Google_Places_Config::get_api_key();

        if ($api_key === '') {
            return [
                'ok'        => false,
                'error'     => 'missing_api_key',
                'processed' => 0,
                'api_calls' => 0,
            ];
        }

        if ($fetch_details === null) {
            $fetch_details = EDP_Google_Places_Config::should_fetch_details();
        }

        $term     = EDP_Google_Places_Config::get_term();
        $per_city = EDP_Google_Places_Config::get_limit();
        $client   = new EDP_Google_Places_Client($api_key);

        $api_calls = 0;
        $messages  = [];

        foreach ($locations as $loc) {
            $location_id = (int) ($loc['id'] ?? 0);

            if ($location_id <= 0) {
                continue;
            }

            $city  = trim((string) ($loc['city_name'] ?? ''));
            $state = strtoupper(trim((string) ($loc['state_id'] ?? '')));

            if ($city === '' || $state === '') {
                $messages[] = sprintf('Skipped location ID %d (empty city/state).', $location_id);
                continue;
            }

            $query  = $term . ' ' . $city . ' ' . $state;
            $search = $client->text_search($query, $per_city);
            ++$api_calls;

            if (is_wp_error($search)) {
                $messages[] = sprintf(
                    'Location %d (%s, %s): %s',
                    $location_id,
                    $city,
                    $state,
                    $search->get_error_message()
                );
                continue;
            }

            /* Places API (New) returns 'places' array */
            $businesses = isset($search['places']) && is_array($search['places'])
                ? $search['places']
                : [];

            EDP_Database::delete_nearby_for_location($location_id, 'google');

            $sort = 0;

            foreach ($businesses as $biz) {
                if (!is_array($biz)) {
                    continue;
                }

                /* New API: 'id' instead of 'place_id' */
                $place_id = isset($biz['id']) ? (string) $biz['id'] : '';

                if ($place_id === '') {
                    continue;
                }

                $phone      = '';
                $hours_text = '';
                $image_url  = '';

                /* Photo from search result — new API uses 'name' resource path */
                $photo_ref = '';

                if (!empty($biz['photos'][0]['name'])) {
                    $photo_ref = (string) $biz['photos'][0]['name'];
                }

                if ($fetch_details) {
                    usleep(100000); // 0.1s between calls

                    $details = $client->place_details($place_id);
                    ++$api_calls;

                    /* New API: details are top-level (no 'result' wrapper) */
                    if (!is_wp_error($details) && is_array($details)) {
                        $det = $details;

                        if (!empty($det['nationalPhoneNumber'])) {
                            $phone = (string) $det['nationalPhoneNumber'];
                        }

                        if (!empty($det['regularOpeningHours']['weekdayDescriptions']) && is_array($det['regularOpeningHours']['weekdayDescriptions'])) {
                            $hours_text = self::format_hours($det['regularOpeningHours']['weekdayDescriptions']);
                        }

                        /* Prefer photo name from details if available */
                        if (!empty($det['photos'][0]['name'])) {
                            $photo_ref = (string) $det['photos'][0]['name'];
                        }
                    }

                    /* Resolve photo to CDN URL */
                    if ($photo_ref !== '') {
                        $image_url = $client->resolve_photo_url($photo_ref, 400);
                        ++$api_calls;
                        usleep(80000);
                    }

                    usleep(80000);
                } elseif ($photo_ref !== '') {
                    $image_url = $client->resolve_photo_url($photo_ref, 400);
                    ++$api_calls;
                }

                /* New API: displayName.text instead of name, userRatingCount instead of user_ratings_total, googleMapsUri instead of url */
                EDP_Database::insert_nearby_row(
                    [
                        'location_id'  => $location_id,
                        'provider'     => 'google',
                        'external_id'  => $place_id,
                        'sort_order'   => $sort,
                        'name'         => isset($biz['displayName']['text']) ? (string) $biz['displayName']['text'] : '',
                        'rating'       => isset($biz['rating']) ? (float) $biz['rating'] : null,
                        'review_count' => isset($biz['userRatingCount']) ? (int) $biz['userRatingCount'] : null,
                        'phone'        => $phone,
                        'image_url'    => $image_url,
                        'hours_text'   => $hours_text,
                        'business_url' => isset($biz['googleMapsUri']) ? (string) $biz['googleMapsUri'] : '',
                        'fetched_at'   => current_time('mysql'),
                    ]
                );

                ++$sort;

                if ($sort >= $per_city) {
                    break;
                }
            }

            usleep(200000); // 0.2s between cities
        }

        return [
            'ok'        => true,
            'processed' => count($locations),
            'api_calls' => $api_calls,
            'messages'  => $messages,
        ];
    }

    /**
     * Convert Google weekday_text array to a single newline-separated string.
     * Returns '24/7' when all days show "24 hours".
     *
     * @param list<string> $weekday_text e.g. ["Monday: 8:00 AM – 6:00 PM", ...]
     */
    private static function format_hours(array $weekday_text): string
    {
        if ($weekday_text === []) {
            return '';
        }

        $all_24 = count(array_filter(
            $weekday_text,
            fn($line) => stripos((string) $line, '24 hours') !== false
        )) === count($weekday_text);

        if ($all_24) {
            return '24/7';
        }

        return implode("\n", array_map('strval', $weekday_text));
    }
}
