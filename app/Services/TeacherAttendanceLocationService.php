<?php

namespace App\Services;

use App\Models\TeacherAttendance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TeacherAttendanceLocationService
{
    /** Return only a valid, actually captured point; zero is a valid coordinate. */
    public function point(TeacherAttendance $attendance, string $side): ?array
    {
        $prefix = match ($side) {
            'check-in' => 'check_in',
            'check-out' => 'check_out',
            default => null,
        };

        if ($prefix === null || ($attendance->attendance_type ?: 'present') !== 'present'
            || $attendance->getAttribute($prefix.'_at') === null
            || $attendance->getAttribute($prefix.'_location_status') !== 'captured') {
            return null;
        }

        $latitude = $attendance->getAttribute($prefix.'_latitude');
        $longitude = $attendance->getAttribute($prefix.'_longitude');
        if (! $this->validCoordinate($latitude, 90) || ! $this->validCoordinate($longitude, 180)) {
            return null;
        }

        $latitude = number_format((float) $latitude, 7, '.', '');
        $longitude = number_format((float) $longitude, 7, '.', '');

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'coordinates' => $latitude.', '.$longitude,
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($latitude.','.$longitude),
        ];
    }

    /** This is deliberately separate from point(): rendering a page never waits for a provider. */
    public function address(array $point): ?string
    {
        if (! config('attendance-location.enabled')
            || ! $this->validCoordinate($point['latitude'] ?? null, 90)
            || ! $this->validCoordinate($point['longitude'] ?? null, 180)) {
            return null;
        }

        $endpoint = config('attendance-location.endpoint');
        if (! is_string($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL)
            || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        $providerKey = hash('sha256', $endpoint);
        $cacheKey = 'dpe.attendance-location.v1.'.hash('sha256', $endpoint.'|'.$point['latitude'].'|'.$point['longitude']);
        $cooldownKey = 'dpe.attendance-location.cooldown.'.$providerKey;
        $failureTtl = max(15, (int) config('attendance-location.failure_cache_seconds', 60));

        try {
            $cache = Cache::store(config('attendance-location.cache_store', 'file'));
            $cached = $cache->get($cacheKey);
            if (is_array($cached) && array_key_exists('address', $cached)) {
                return is_string($cached['address']) ? $cached['address'] : null;
            }
            if ($cache->has($cooldownKey)) {
                return null;
            }

            // Do not wait for duplicate requests, or send uncached requests if the cache is unavailable.
            $lock = $cache->lock($cacheKey.':lock', 10);
            if (! $lock->get()) {
                return null;
            }

            try {
                $cached = $cache->get($cacheKey);
                if (is_array($cached) && array_key_exists('address', $cached)) {
                    return is_string($cached['address']) ? $cached['address'] : null;
                }

                $address = null;
                try {
                    $response = Http::acceptJson()
                        ->withUserAgent('DPE-Attendance/1.0')
                        ->connectTimeout(2)->timeout(4)
                        ->withOptions(['allow_redirects' => false])
                        ->get($endpoint, [
                            'lat' => $point['latitude'],
                            'lon' => $point['longitude'],
                            'limit' => 1,
                            'radius' => 0.5,
                        ]);

                    if ($response->status() === 200 && strlen($response->body()) <= 65536) {
                        $address = $this->formatAddress($response->json('features.0'), $point);
                    } elseif ($response->status() === 429 || $response->serverError()) {
                        $cache->put($cooldownKey, true, $failureTtl);
                    }
                } catch (Throwable) {
                    // Never log provider URLs containing a Teacher's coordinates.
                    $cache->put($cooldownKey, true, $failureTtl);
                }

                $cache->put($cacheKey, ['address' => $address], $address === null
                    ? $failureTtl : max(60, (int) config('attendance-location.cache_seconds', 604800)));

                return $address;
            } finally {
                $lock->release();
            }
        } catch (Throwable) {
            return null;
        }
    }

    private function formatAddress(mixed $feature, array $point): ?string
    {
        if (! is_array($feature) || ($feature['geometry']['type'] ?? null) !== 'Point') {
            return null;
        }
        $coordinates = $feature['geometry']['coordinates'] ?? null;
        $properties = $feature['properties'] ?? null;
        if (! is_array($coordinates) || ! is_array($properties)
            || ! $this->validCoordinate($coordinates[0] ?? null, 180)
            || ! $this->validCoordinate($coordinates[1] ?? null, 90)) {
            return null;
        }

        // A nearby map object is an approximate address, never proof of an exact building.
        $lat1 = deg2rad((float) $point['latitude']);
        $lat2 = deg2rad((float) $coordinates[1]);
        $a = sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(deg2rad((float) $coordinates[0] - (float) $point['longitude']) / 2) ** 2;
        if (6371000 * 2 * asin(sqrt(min(1, max(0, $a)))) > 500) {
            return null;
        }

        $parts = [];
        foreach (['name', 'street', 'housenumber', 'locality', 'district', 'city', 'county', 'state', 'postcode', 'country'] as $field) {
            $value = $properties[$field] ?? null;
            $parts[$field] = is_string($value) && mb_check_encoding($value, 'UTF-8')
                ? trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 160))
                : '';
        }
        if (implode('', array_intersect_key($parts, array_flip(['name', 'street', 'locality', 'district', 'city', 'county']))) === '') {
            return null;
        }

        $road = trim($parts['street'].' '.$parts['housenumber']);
        $addressParts = [$parts['name'] === $parts['street'] ? '' : $parts['name'], $road];
        foreach (['locality', 'district', 'city', 'county', 'state', 'postcode', 'country'] as $field) {
            $addressParts[] = $parts[$field];
        }
        $unique = [];
        foreach ($addressParts as $part) {
            if ($part !== '') {
                $unique[mb_strtolower($part)] = $part;
            }
        }

        return $unique === [] ? null : mb_substr(implode(', ', $unique), 0, 600);
    }

    private function validCoordinate(mixed $value, int $limit): bool
    {
        return (is_int($value) || is_float($value) || is_string($value))
            && is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= $limit;
    }
}
