<?php

namespace App\Domain\Content;

/**
 * Conservative interpretation of a legacy free-text Exhibition location.
 * Never geocodes, invents a country, or overwrites an ambiguous address.
 */
final class ExhibitionAddressNormalizer
{
    /**
     * @return array{location_text:?string,postal_code:?string,city:?string}|null
     */
    public function parse(?string $input): ?array
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        // German legacy snapshot: "Venue, Street 12, 12345 City".
        if (preg_match('/^(?<street>.+),\s*(?<zip>[0-9]{5})\s+(?<city>[^,]+)$/u', $raw, $parts) === 1) {
            return $this->fields(trim($parts['street']), $parts['zip'], trim($parts['city']));
        }

        // An address without a postal code can be separated if a house
        // number is present in the street side of its last comma.
        if (
            preg_match('/^(?<street>.+),\s*(?<city>[\p{L}][\p{L}\p{M} .\-]+)$/u', $raw, $parts) === 1
            && preg_match('/\b[0-9]{1,4}\b/u', $parts['street']) === 1
        ) {
            return $this->fields(trim($parts['street']), null, trim($parts['city']));
        }

        // Single-word or multi-word city-only historical entries.
        if (preg_match('/^[\p{L}][\p{L}\p{M} .\-]*$/u', $raw) === 1 && mb_strlen($raw) <= 100) {
            return $this->fields(null, null, $raw);
        }

        // A street with no recognizable city, combined exhibition venues,
        // or inconsistent punctuation remains untouched for manual review.
        return null;
    }

    /** @return array{location_text:?string,postal_code:?string,city:?string}|null */
    private function fields(?string $street, ?string $postal, ?string $city): ?array
    {
        if ($city === null || $city === '') {
            return null;
        }

        return [
            'location_text' => $street === '' ? null : $street,
            'postal_code' => $postal,
            'city' => $city,
        ];
    }
}
