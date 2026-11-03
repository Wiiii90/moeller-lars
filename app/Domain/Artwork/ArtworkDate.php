<?php

namespace App\Domain\Artwork;

use App\Models\Artwork;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

/**
 * A single partial-date contract for Artwork editing. Month precision uses the
 * first day of that month only as an internal database carrier; the declared
 * precision, display and ordering must never present that day as artist-entered.
 */
final class ArtworkDate
{
    public static function fromArtwork(Artwork $artwork): ?string
    {
        $workDate = $artwork->getAttribute('work_date');
        $workYear = $artwork->getAttribute('work_year');
        $precision = (string) $artwork->getAttribute('date_precision');

        if ($workDate instanceof DateTimeInterface) {
            return $precision === 'month'
                ? $workDate->format('Y-m')
                : $workDate->format('Y-m-d');
        }

        return is_numeric($workYear) && (int) $workYear >= 1000 && (int) $workYear <= 9999
            ? (string) (int) $workYear
            : null;
    }

    /** @return array{work_year:?int,work_date:?string,date_precision:string} */
    public static function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['work_year' => null, 'work_date' => null, 'date_precision' => 'unknown'];
        }

        if (! is_string($value) || preg_match('/^([1-9][0-9]{3})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/D', $value, $matches) !== 1) {
            throw ValidationException::withMessages([
                'artwork_date' => 'Choose a year, a month and year, or an exact date.',
            ]);
        }

        $year = (int) $matches[1];
        $month = isset($matches[2]) ? (int) $matches[2] : null;
        $day = isset($matches[3]) ? (int) $matches[3] : null;

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw ValidationException::withMessages(['artwork_date' => 'Choose a valid month.']);
        }
        if ($day !== null && ! checkdate((int) $month, $day, $year)) {
            throw ValidationException::withMessages(['artwork_date' => 'Choose a valid calendar date.']);
        }

        return [
            'work_year' => $year,
            'work_date' => $month === null ? null : sprintf('%04d-%02d-%02d', $year, $month, $day ?? 1),
            'date_precision' => $month === null ? 'year' : ($day === null ? 'month' : 'day'),
        ];
    }

    public static function precisionFromFields(array $fields): string
    {
        if (filled($fields['date_precision'] ?? null)) {
            return (string) $fields['date_precision'];
        }

        return filled($fields['work_date'] ?? null)
            ? 'day'
            : (filled($fields['work_year'] ?? null) ? 'year' : 'unknown');
    }
}
