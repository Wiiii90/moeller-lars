<?php

namespace App\Domain\Content;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ExhibitionGeocodingService
{
    private const CACHE_VERSION = 'v2';

    public function __construct(
        private readonly NominatimRequestThrottle $throttle,
    ) {}

    /** @return array{label:string, latitude:float, longitude:float}|null */
    public function locate(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $endpoint = trim((string) config('services.nominatim.endpoint'));
        $userAgent = trim((string) config('services.nominatim.user_agent'));
        if ($endpoint === '' || $userAgent === '') {
            throw new ExhibitionGeocodingUnavailable('Nominatim endpoint or User-Agent is not configured.');
        }

        $request = Http::acceptJson()
            ->withHeaders(['User-Agent' => $userAgent])
            ->connectTimeout(2)
            ->timeout(5);

        foreach ($this->queriesFor($address) as $query) {
            $cacheKey = $this->cacheKey($endpoint, $query);
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('found', $cached)) {
                if (($cached['found'] ?? false) === true && is_array($cached['result'] ?? null)) {
                    /** @var array{label:string, latitude:float, longitude:float} $result */
                    $result = $cached['result'];
                    return $result;
                }

                continue;
            }

            $result = $this->throttle->run(
                $endpoint,
                fn (): ?array => $this->request($request, $endpoint, $query),
            );
            Cache::put(
                $cacheKey,
                ['found' => $result !== null, 'result' => $result],
                now()->addSeconds($result === null ? $this->missCacheSeconds() : $this->hitCacheSeconds()),
            );

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /** @return list<array<string, int|string>> */
    private function queriesFor(string $address): array
    {
        $parts = collect(explode(',', $address))
            ->map(static fn (string $part): string => trim($part))
            ->filter()
            ->values();
        $queries = [];

        // Editorial input is normalized as "Street address, City, Country".
        // Prefer Nominatim's structured search when all three parts are available,
        // then make one bounded free-text fallback for addresses with unusual OSM data.
        if ($parts->count() >= 3) {
            $country = (string) $parts->pop();
            $city = (string) $parts->pop();
            $street = $parts->implode(', ');
            if ($street !== '' && $city !== '' && $country !== '') {
                $queries[] = [
                    'street' => $street,
                    'city' => $city,
                    'country' => $country,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 0,
                ];
            }
        }

        $queries[] = [
            'q' => $address,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 0,
        ];

        $email = trim((string) config('services.nominatim.email'));
        if ($email !== '') {
            foreach ($queries as &$query) {
                $query['email'] = $email;
            }
            unset($query);
        }

        return collect($queries)
            ->unique(static fn (array $query): string => http_build_query($query, '', '&', PHP_QUERY_RFC3986))
            ->values()
            ->all();
    }

    /** @param array<string, int|string> $query
     *  @return array{label:string, latitude:float, longitude:float}|null
     */
    private function request(PendingRequest $request, string $endpoint, array $query): ?array
    {
        try {
            $response = $request->get($endpoint, $query);
            if (! $response->successful()) {
                throw new ExhibitionGeocodingUnavailable('Nominatim returned HTTP '.$response->status().'.');
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new ExhibitionGeocodingUnavailable('Nominatim returned an invalid JSON payload.');
            }
            if ($payload === []) {
                return null;
            }

            $candidate = $payload[0] ?? null;
            if (! is_array($candidate)) {
                throw new ExhibitionGeocodingUnavailable('Nominatim returned an invalid result row.');
            }

            $latitude = filter_var($candidate['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($candidate['lon'] ?? null, FILTER_VALIDATE_FLOAT);
            $label = trim((string) ($candidate['display_name'] ?? ''));
            if (
                $latitude === false
                || $longitude === false
                || $label === ''
                || $latitude < -90
                || $latitude > 90
                || $longitude < -180
                || $longitude > 180
            ) {
                throw new ExhibitionGeocodingUnavailable('Nominatim returned invalid coordinates.');
            }

            return [
                'label' => $label,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        } catch (ExhibitionGeocodingUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ExhibitionGeocodingUnavailable('Nominatim request failed.', previous: $exception);
        }
    }

    /** @param array<string, int|string> $query */
    private function cacheKey(string $endpoint, array $query): string
    {
        ksort($query);

        return 'journal:nominatim:'.self::CACHE_VERSION.':'.sha1($endpoint.'|'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function hitCacheSeconds(): int
    {
        return max(86400, (int) config('services.nominatim.hit_cache_seconds', 2592000));
    }

    private function missCacheSeconds(): int
    {
        return max(300, (int) config('services.nominatim.miss_cache_seconds', 86400));
    }
}
