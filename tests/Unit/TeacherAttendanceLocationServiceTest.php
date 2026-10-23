<?php

namespace Tests\Unit;

use App\Models\TeacherAttendance;
use App\Services\TeacherAttendanceLocationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherAttendanceLocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance-location.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): TeacherAttendanceLocationService
    {
        return app(TeacherAttendanceLocationService::class);
    }

    private function point(): array
    {
        return ['latitude' => '-8.6500000', 'longitude' => '115.2166667'];
    }

    private function providerResult(array $properties = [], array $coordinates = [115.2166667, -8.65]): array
    {
        return ['features' => [[
            'geometry' => ['type' => 'Point', 'coordinates' => $coordinates],
            'properties' => $properties ?: ['street' => 'Jalan Contoh', 'housenumber' => '12', 'city' => 'Denpasar', 'state' => 'Bali', 'country' => 'Indonesia'],
        ]]];
    }

    #[DataProvider('validPoints')]
    public function test_map_keeps_saved_coordinates_including_zero(string $latitude, string $longitude): void
    {
        $attendance = new TeacherAttendance([
            'check_in_at' => '2026-10-16 09:00:00', 'check_in_location_status' => 'captured',
            'check_in_latitude' => $latitude, 'check_in_longitude' => $longitude,
        ]);
        $point = $this->service()->point($attendance, 'check-in');
        $this->assertNotNull($point);
        parse_str(parse_url($point['map_url'], PHP_URL_QUERY), $query);
        $this->assertSame($latitude.','.$longitude, $query['query']);
        $this->assertSame('1', $query['api']);
        $this->assertSame('www.google.com', parse_url($point['map_url'], PHP_URL_HOST));
        Http::assertNothingSent();
    }

    public static function validPoints(): array
    {
        return [['-8.6500000', '115.2166667'], ['0.0000000', '0.0000000'], ['0.0000000', '115.0000000'], ['-8.0000000', '0.0000000']];
    }

    public function test_address_is_cached_and_request_contains_only_coordinates_and_search_options(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response($this->providerResult())]);
        $expected = 'Jalan Contoh 12, Denpasar, Bali, Indonesia';
        $this->assertSame($expected, $this->service()->address($this->point()));
        $this->assertSame($expected, $this->service()->address($this->point()));
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && count($request->data()) === 4
                && $request['lat'] === '-8.6500000' && $request['lon'] === '115.2166667'
                && $request['limit'] === 1 && $request['radius'] === 0.5;
        });
        $this->travel(8)->days();
        $this->assertSame($expected, $this->service()->address($this->point()));
        Http::assertSentCount(2);
    }

    public function test_two_distinct_points_and_provider_change_cannot_reuse_wrong_address(): void
    {
        Http::fake(fn ($request) => Http::response($this->providerResult(
            ['street' => $request['lon'] === '115.2170000' ? 'Jalan Kedua' : 'Jalan Pertama'],
            [(float) $request['lon'], (float) $request['lat']]
        )));
        $this->assertSame('Jalan Pertama', $this->service()->address($this->point()));
        $this->assertSame('Jalan Kedua', $this->service()->address(['latitude' => '-8.6500000', 'longitude' => '115.2170000']));
        config(['attendance-location.endpoint' => 'https://example.test/reverse']);
        $this->assertSame('Jalan Pertama', $this->service()->address($this->point()));
        Http::assertSentCount(3);
    }

    public function test_file_cache_reuses_address_across_independent_requests(): void
    {
        $directory = sys_get_temp_dir().'/dpe-location-test-'.Str::uuid();
        config([
            'cache.stores.attendance_location_test' => ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory],
            'attendance-location.cache_store' => 'attendance_location_test',
        ]);
        Http::fake(['*' => Http::response($this->providerResult())]);
        try {
            $first = $this->service()->address($this->point());
            Cache::forgetDriver('attendance_location_test');
            $this->assertNotNull($first);
            $this->assertSame($first, $this->service()->address($this->point()));
            Http::assertSentCount(1);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    #[DataProvider('unusableResponses')]
    public function test_bad_results_fall_back_and_are_temporarily_cached(mixed $body, int $status): void
    {
        Http::fake(fn () => Http::response($body, $status));
        $this->assertNull($this->service()->address($this->point()));
        $this->assertNull($this->service()->address($this->point()));
        Http::assertSentCount(1);
    }

    public static function unusableResponses(): array
    {
        return [
            'no matches' => [['features' => []], 200],
            'malformed json' => ['<html>maintenance</html>', 200],
            'missing point' => [['features' => [['properties' => ['street' => 'Wrong Street']]]], 200],
            'far away' => [['features' => [['geometry' => ['type' => 'Point', 'coordinates' => [0, 0]], 'properties' => ['street' => 'Wrong Street']]]], 200],
            'province only' => [['features' => [['geometry' => ['type' => 'Point', 'coordinates' => [115.2166667, -8.65]], 'properties' => ['country' => 'Indonesia', 'state' => 'Bali']]]], 200],
            'nested properties' => [['features' => [['geometry' => ['type' => 'Point', 'coordinates' => [115.2166667, -8.65]], 'properties' => ['street' => ['invalid'], 'city' => false]]]], 200],
            'oversized body' => [str_repeat('a', 65537), 200],
            'throttled' => [[], 429],
            'server unavailable' => [[], 503],
            'redirect not followed' => [[], 302],
        ];
    }

    public function test_timeout_returns_no_address_and_cooldown_prevents_an_outage_request_storm(): void
    {
        $unavailable = true;
        $attempts = 0;
        Http::fake(function () use (&$unavailable, &$attempts) {
            $attempts++;
            if ($unavailable) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response($this->providerResult());
        });
        $this->assertNull($this->service()->address($this->point()));
        // A second point should not hit the same failing provider either.
        $this->assertNull($this->service()->address(['latitude' => '-8.6510000', 'longitude' => '115.2170000']));
        $unavailable = false;
        $this->assertNull($this->service()->address($this->point()));
        $this->assertSame(1, $attempts);
        $this->travel(61)->seconds();
        $this->assertNotNull($this->service()->address($this->point()));
        $this->assertSame(2, $attempts);
    }

    public function test_unavailable_cache_or_disabled_provider_does_not_break_the_page(): void
    {
        Http::fake();
        config(['attendance-location.cache_store' => 'not-configured']);
        $this->assertNull($this->service()->address($this->point()));
        config(['attendance-location.cache_store' => 'array', 'attendance-location.enabled' => false]);
        $this->assertNull($this->service()->address($this->point()));
        config(['attendance-location.enabled' => true, 'attendance-location.endpoint' => 'http://example.test/reverse']);
        $this->assertNull($this->service()->address($this->point()));
        Http::assertNothingSent();
    }

    public function test_provider_text_is_normalized_and_duplicate_city_names_removed(): void
    {
        Http::fake(['*' => Http::response($this->providerResult([
            'street' => '<b>Jalan Contoh</b>', 'name' => 'Jalan Contoh', 'housenumber' => '12',
            'district' => 'Denpasar', 'city' => 'Denpasar', 'state' => "  Bali\n ", 'country' => 'Indonesia',
        ]))]);
        $this->assertSame('Jalan Contoh 12, Denpasar, Bali, Indonesia', $this->service()->address($this->point()));
    }
}
