<?php

namespace App\Domain\Content;

/**
 * An exhibition map needs a place within a country, not merely a country
 * or an ambiguous street name. A street number is optional for city-level
 * exhibitions; Nominatim still decides whether a usable location exists.
 */
final class ExhibitionMapAddress
{
    /** @param array<string, mixed> $address */
    public static function isSufficient(array $address): bool
    {
        $country = trim((string) ($address['country'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $postalCode = trim((string) ($address['postal_code'] ?? ''));

        return $country !== '' && ($city !== '' || $postalCode !== '');
    }
}
