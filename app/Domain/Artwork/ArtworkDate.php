<?php

namespace App\Domain\Artwork;

use App\Domain\Content\RegionalFormats;
use App\Models\Artwork;
use Illuminate\Validation\ValidationException;

/** One Artwork date: year, optional month, optional day. No invented date. */
final class ArtworkDate
{
    public static function fromArtwork(Artwork $artwork): ?string
    {
        $year = $artwork->getAttribute('year');
        if ($year === null) {
            return null;
        }
        $month = $artwork->getAttribute('month');
        if ($month === null) {
            return sprintf('%04d', (int) $year);
        }
        $day = $artwork->getAttribute('day');

        return $day === null
            ? sprintf('%04d-%02d', (int) $year, (int) $month)
            : sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
    }

    public static function display(Artwork $artwork, ?string $region = null): ?string
    {
        $year = $artwork->getAttribute('year');
        if ($year === null) {
            return null;
        }
        $month = $artwork->getAttribute('month');
        if ($month === null) {
            return (string) $year;
        }
        $region = RegionalFormats::region($region ?? RegionalFormats::publicRegion());
        $y = sprintf('%04d', (int) $year);
        $m = sprintf('%02d', (int) $month);
        $day = $artwork->getAttribute('day');
        if ($day === null) {
            return $region === 'de-DE' ? $m.'.'.$y : $m.'/'.$y;
        }
        $d = sprintf('%02d', (int) $day);

        return match ($region) {
            'de-DE' => $d.'.'.$m.'.'.$y,
            'en-GB' => $d.'/'.$m.'/'.$y,
            'en-US' => $m.'/'.$d.'/'.$y,
        };
    }

    /** @return array{year:?int,month:?int,day:?int} */
    public static function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['year' => null, 'month' => null, 'day' => null];
        }
        if (! is_string($value) || preg_match('/^([1-9][0-9]{3})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/D', $value, $matches) !== 1) {
            throw ValidationException::withMessages(['artwork_date' => 'Choose a year, a month and year, or an exact date.']);
        }
        $parts = [
            'year' => (int) $matches[1],
            'month' => isset($matches[2]) ? (int) $matches[2] : null,
            'day' => isset($matches[3]) ? (int) $matches[3] : null,
        ];
        self::validateParts($parts);

        return $parts;
    }

    /** @param array{year:mixed,month:mixed,day:mixed} $parts */
    public static function validateParts(array $parts): void
    {
        $year = $parts['year'];
        $month = $parts['month'];
        $day = $parts['day'];
        if ($year !== null && ((int) $year < 1000 || (int) $year > 9999)) {
            throw ValidationException::withMessages(['year' => 'Choose a four-digit year.']);
        }
        if ($month !== null && ($year === null || (int) $month < 1 || (int) $month > 12)) {
            throw ValidationException::withMessages(['month' => 'Choose a valid month and year.']);
        }
        if ($day !== null && ($month === null || $year === null || ! checkdate((int) $month, (int) $day, (int) $year))) {
            throw ValidationException::withMessages(['day' => 'Choose a valid calendar date.']);
        }
    }
}
