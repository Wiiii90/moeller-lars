<?php

namespace App\Domain\Content;

use Illuminate\Support\Facades\Http;
use Throwable;

final class ExhibitionGeocodingService
{
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

        try {
            $request = Http::acceptJson()
                ->withHeaders(['User-Agent' => $userAgent])
                ->connectTimeout(2)
                ->timeout(5);
            $email = trim((string) config('services.nominatim.email'));
            $response = $request->get($endpoint, array_filter([
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 0,
                'email' => $email !== '' ? $email : null,
            ], static fn (mixed $value): bool => $value !== null));

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
}
