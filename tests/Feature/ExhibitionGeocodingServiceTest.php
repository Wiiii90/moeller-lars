<?php

use App\Domain\Content\ExhibitionGeocodingService;
use App\Domain\Content\ExhibitionGeocodingUnavailable;
use App\Domain\Content\NominatimRequestThrottle;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function useFakeNominatimThrottle(int &$clock, array &$sleeps): NominatimRequestThrottle
{
    $throttle = new NominatimRequestThrottle(
        clock: static function () use (&$clock): int { return $clock; },
        sleep: static function (int $milliseconds) use (&$clock, &$sleeps): void {
            $sleeps[] = $milliseconds;
            $clock += $milliseconds;
        },
    );
    app()->instance(NominatimRequestThrottle::class, $throttle);

    return $throttle;
}

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'services.nominatim.endpoint' => 'https://nominatim.example.test/search',
        'services.nominatim.user_agent' => 'moeller-lars test geocoder',
        'services.nominatim.email' => null,
        'services.nominatim.min_interval_ms' => 1000,
        'services.nominatim.lock_seconds' => 5,
        'services.nominatim.lock_wait_seconds' => 0,
    ]);
    Cache::flush();
});

it('throttles the bounded structured miss to free-text fallback without real sleeping', function (): void {
    $clock = 0;
    $sleeps = [];
    useFakeNominatimThrottle($clock, $sleeps);
    Http::fakeSequence()
        ->push([], 200)
        ->push([[
            'lat' => '53.550341',
            'lon' => '9.992477',
            'display_name' => 'Rathausmarkt 1, Hamburg, Deutschland',
        ]], 200);

    $match = app(ExhibitionGeocodingService::class)->locate('Rathausmarkt 1, Hamburg, Deutschland');
    $requests = Http::recorded()->map(static fn (array $pair): Request => $pair[0])->values();

    expect($match)->not->toBeNull()
        ->and($match['latitude'])->toBe(53.550341)
        ->and($match['longitude'])->toBe(9.992477)
        ->and($requests)->toHaveCount(2)
        ->and($requests[0]['street'])->toBe('Rathausmarkt 1')
        ->and($requests[0]['city'])->toBe('Hamburg')
        ->and($requests[0]['country'])->toBe('Deutschland')
        ->and($requests[0]['q'])->toBeNull()
        ->and($requests[1]['q'])->toBe('Rathausmarkt 1, Hamburg, Deutschland')
        ->and($sleeps)->toBe([1000]);
});

it('throttles separate locate calls through shared cache state', function (): void {
    $clock = 0;
    $sleeps = [];
    $firstThrottle = useFakeNominatimThrottle($clock, $sleeps);
    Http::fakeSequence()
        ->push([[
            'lat' => '53.550341',
            'lon' => '9.992477',
            'display_name' => 'Rathausmarkt 1, Hamburg, Deutschland',
        ]], 200)
        ->push([[
            'lat' => '53.551000',
            'lon' => '9.993000',
            'display_name' => 'Mönckebergstraße 1, Hamburg, Deutschland',
        ]], 200);

    (new ExhibitionGeocodingService($firstThrottle))->locate('Rathausmarkt 1, Hamburg, Deutschland');
    $secondThrottle = useFakeNominatimThrottle($clock, $sleeps);
    (new ExhibitionGeocodingService($secondThrottle))->locate('Mönckebergstraße 1, Hamburg, Deutschland');

    Http::assertSentCount(2);
    expect($sleeps)->toBe([1000])
        ->and(Cache::get(NominatimRequestThrottle::lastRequestKeyFor(config('services.nominatim.endpoint'))))->toBe(1000);
});

it('uses a cached hit without another request or throttle sleep', function (): void {
    $clock = 0;
    $sleeps = [];
    useFakeNominatimThrottle($clock, $sleeps);
    Http::fake(fn (): \Illuminate\Http\Client\Response => Http::response([[
        'lat' => '53.550341',
        'lon' => '9.992477',
        'display_name' => 'Rathausmarkt 1, Hamburg, Deutschland',
    ]], 200));

    $service = app(ExhibitionGeocodingService::class);
    $first = $service->locate('Rathausmarkt 1, Hamburg, Deutschland');
    $second = $service->locate('Rathausmarkt 1, Hamburg, Deutschland');

    expect($first)->toBe($second)
        ->and($sleeps)->toBe([]);
    Http::assertSentCount(1);
});

it('uses cached no-match candidates without another request or throttle sleep', function (): void {
    $clock = 0;
    $sleeps = [];
    useFakeNominatimThrottle($clock, $sleeps);
    Http::fakeSequence()->push([], 200)->push([], 200);

    $service = app(ExhibitionGeocodingService::class);
    expect($service->locate('Rathausmarkt 1, Hamburg, Deutschland'))->toBeNull();
    $sleepCount = count($sleeps);
    expect($service->locate('Rathausmarkt 1, Hamburg, Deutschland'))->toBeNull()
        ->and($sleeps)->toHaveCount($sleepCount)
        ->and($sleeps)->toBe([1000]);
    Http::assertSentCount(2);
});

it('keeps service failures distinct from a no-match result', function (): void {
    $clock = 0;
    $sleeps = [];
    useFakeNominatimThrottle($clock, $sleeps);
    Http::fake(fn (): \Illuminate\Http\Client\Response => Http::response([], 503));

    expect(fn () => app(ExhibitionGeocodingService::class)->locate('Rathausmarkt 1, Hamburg, Deutschland'))
        ->toThrow(ExhibitionGeocodingUnavailable::class, 'Nominatim returned HTTP 503.');
    Http::assertSentCount(1);
});

it('fails closed when the shared Nominatim lock is already held', function (): void {
    $clock = 0;
    $sleeps = [];
    useFakeNominatimThrottle($clock, $sleeps);
    Http::fake();

    $endpoint = (string) config('services.nominatim.endpoint');
    $lock = Cache::lock(NominatimRequestThrottle::lockKeyFor($endpoint), 5);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(ExhibitionGeocodingService::class)->locate('Rathausmarkt 1, Hamburg, Deutschland'))
            ->toThrow(ExhibitionGeocodingUnavailable::class, 'Nominatim request throttle is busy.');
        Http::assertSentCount(0);
    } finally {
        $lock->release();
    }
});
