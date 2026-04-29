<?php
/**
 * Google Places API credentials and search defaults.
 *
 * @package EmergencyDentalPros
 */

if (!defined('ABSPATH')) {
    exit;
}

final class EDP_Google_Places_Config
{
    public const OPTION_KEY = 'edp_google_places_settings';

    /**
     * @return array{api_key:string, term:string, limit:int, fetch_details:bool}
     */
    public static function get_all(): array
    {
        $defaults = [
            'api_key'      => '',
            'term'         => 'emergency dentist',
            'limit'        => 5,
            'fetch_details' => true,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            return $defaults;
        }

        return array_replace($defaults, $saved);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): void
    {
        $current = self::get_all();

        if (isset($data['term'])) {
            $current['term'] = sanitize_text_field((string) $data['term']);
        }

        if (isset($data['limit'])) {
            $lim = (int) $data['limit'];
            $current['limit'] = max(1, min(20, $lim));
        }

        if (array_key_exists('fetch_details', $data)) {
            $current['fetch_details'] = (bool) $data['fetch_details'];
        }

        if (isset($data['api_key'])) {
            $key = is_string($data['api_key']) ? trim($data['api_key']) : '';

            if ($key !== '') {
                $current['api_key'] = $key;
            }
        }

        update_option(self::OPTION_KEY, $current, false);
    }

    public static function get_api_key(): string
    {
        if (defined('EDP_GOOGLE_PLACES_API_KEY') && is_string(EDP_GOOGLE_PLACES_API_KEY) && EDP_GOOGLE_PLACES_API_KEY !== '') {
            return EDP_GOOGLE_PLACES_API_KEY;
        }

        $all = self::get_all();

        return (string) ($all['api_key'] ?? '');
    }

    public static function get_term(): string
    {
        $t = (string) (self::get_all()['term'] ?? 'emergency dentist');

        return $t !== '' ? $t : 'emergency dentist';
    }

    public static function get_limit(): int
    {
        $lim = (int) (self::get_all()['limit'] ?? 5);

        return max(1, min(20, $lim));
    }

    public static function should_fetch_details(): bool
    {
        return (bool) (self::get_all()['fetch_details'] ?? true);
    }
}
