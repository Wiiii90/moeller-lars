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

        try {
            $request = Http::acceptJson()
                ->withHeaders(['User-Agent' => (string) config('services.nominatim.user_agent')])
                ->timeout(5);
            $email = trim((string) config('services.nominatim.email'));
            $response = $request->get((string) config('services.nominatim.endpoint'), array_filter([
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 0,
                'email' => $email !== '' ? $email : null,
            ]));

            if (! $response->successful()) {
                return null;
            }

            $candidate = ((array) $response->json())[0] ?? null;
            if (! is_array($candidate)) {
                return null;
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
                return null;
            }

            return [
                'label' => $label,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
