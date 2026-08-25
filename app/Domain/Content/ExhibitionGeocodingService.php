<?php

namespace App\Domain\Content;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ExhibitionGeocodingService
{
    /** @return array<string, string> encoded candidate => label */
    public function options(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            return [];
        }

        try {
            $request = Http::acceptJson()
                ->withHeaders(['User-Agent' => (string) config('services.nominatim.user_agent')])
                ->timeout(5);
            $email = trim((string) config('services.nominatim.email'));
            $response = $request->get((string) config('services.nominatim.endpoint'), array_filter([
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 5,
                'addressdetails' => 0,
                'email' => $email !== '' ? $email : null,
            ]));
            if (! $response->successful()) {
                return [];
            }

            $options = [];
            foreach ((array) $response->json() as $candidate) {
                if (! is_array($candidate)) {
                    continue;
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
                    continue;
                }

                $options[$this->encode([
                    'address' => $label,
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                ])] = $label;
            }

            return $options;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{address:string, latitude:float, longitude:float} */
    public function decode(string $encoded): array
    {
        $normalized = strtr($encoded, '-_', '+/');
        $remainder = strlen($normalized) % 4;
        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $raw = base64_decode($normalized, true);
        $candidate = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($candidate)) {
            throw ValidationException::withMessages([
                'address_candidate' => 'Choose a valid address result.',
            ]);
        }

        $address = trim((string) ($candidate['address'] ?? ''));
        $latitude = filter_var($candidate['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($candidate['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if (
            $address === ''
            || $latitude === false
            || $longitude === false
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
        ) {
            throw ValidationException::withMessages([
                'address_candidate' => 'Choose a valid address result.',
            ]);
        }

        return [
            'address' => $address,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }

    /** @param array{address:string, latitude:float, longitude:float} $candidate */
    private function encode(array $candidate): string
    {
        return rtrim(strtr(base64_encode(json_encode($candidate, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
}
