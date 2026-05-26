<?php

declare(strict_types=1);

namespace Mercato\Core\Geo;

/**
 * Thin geocoder using the OpenStreetMap Nominatim service (free, no API
 * key, OSM ToS allows reasonable use with a User-Agent identifying the app).
 *
 * Two responsibilities:
 *   - geocode(string $query): convert a postal code / city / address to {lat,lng}.
 *   - distanceKm(...): great-circle (Haversine) distance between two points.
 *
 * The geocoder is intentionally pessimistic: any non-200, any malformed
 * JSON, any zero-result response returns null. Callers must handle the
 * null path (e.g. fall back to non-geo search). Results are short-cached
 * in a WP transient so repeated submissions don't hammer Nominatim.
 */
final class Geocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'Mercato/1.0 (https://mercato.local; mercato-core)';
    private const CACHE_TTL_SECONDS = 3600;
    private const TIMEOUT_SECONDS = 4;

    /**
     * @return array{lat:float,lng:float,display:string}|null
     */
    public function geocode(string $query): ?array
    {
        $query = \trim($query);
        if ($query === '' || \strlen($query) > 200) {
            return null;
        }

        $cacheKey = 'mercato_geo_' . \md5($query);
        if (\function_exists('get_transient')) {
            $cached = \get_transient($cacheKey);
            if (\is_array($cached) && isset($cached['lat'], $cached['lng'])) {
                return $cached;
            }
        }

        if (!\function_exists('wp_remote_get')) {
            return null;
        }

        $url = self::ENDPOINT . '?' . \http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 0,
        ]);

        $response = \wp_remote_get($url, [
            'timeout' => self::TIMEOUT_SECONDS,
            'redirection' => 2,
            'user-agent' => self::USER_AGENT,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (\is_wp_error($response)) {
            return null;
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = (string) \wp_remote_retrieve_body($response);
        $decoded = \json_decode($body, true);
        if (!\is_array($decoded) || $decoded === []) {
            return null;
        }

        $first = $decoded[0];
        if (!isset($first['lat'], $first['lon'])) {
            return null;
        }

        $result = [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
            'display' => isset($first['display_name']) ? (string) $first['display_name'] : $query,
        ];

        if (\function_exists('set_transient')) {
            \set_transient($cacheKey, $result, self::CACHE_TTL_SECONDS);
        }

        return $result;
    }

    /**
     * Great-circle distance in kilometers between two lat/lng pairs.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0088;
        $dLat = \deg2rad($lat2 - $lat1);
        $dLng = \deg2rad($lng2 - $lng1);
        $a = \sin($dLat / 2) ** 2
            + \cos(\deg2rad($lat1)) * \cos(\deg2rad($lat2)) * \sin($dLng / 2) ** 2;
        $c = 2 * \atan2(\sqrt($a), \sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }
}
