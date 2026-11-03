<?php

namespace App\Domain\Content;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Canonical year, year-month or exact-day expression for Exhibition ranges. */
final class ExhibitionDate
{
    public static function normalize(mixed $value, string $field = 'date'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (! is_string($value) || preg_match('/^([1-9][0-9]{3})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/D', $value, $parts) !== 1) {
            throw new InvalidArgumentException("Invalid {$field}: expected YYYY, YYYY-MM or YYYY-MM-DD.");
        }
        if (isset($parts[2]) && ((int) $parts[2] < 1 || (int) $parts[2] > 12)) {
            throw new InvalidArgumentException("Invalid month in {$field}.");
        }
        if (isset($parts[3]) && ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new InvalidArgumentException("Invalid calendar day in {$field}.");
        }

        return $value;
    }

    /**
     * Strict projection of historical authored strings, never an imprecise
     * guess. A retained publication payload must keep its original checksum.
     *
     * @return array{starts_on:?string,ends_on:?string}
     */
    public static function fromLegacy(mixed $start, mixed $end, mixed $authored): array
    {
        $structuredStart = self::normalize($start, 'stored start');
        $structuredEnd = self::normalize($end, 'stored end');
        $text = trim((string) ($authored ?? ''));
        if ($text === '') {
            if (! self::validRange($structuredStart, $structuredEnd)) {
                throw new InvalidArgumentException('Stored exhibition end precedes the start or has no start.');
            }

            return ['starts_on' => $structuredStart, 'ends_on' => $structuredEnd];
        }

        if (preg_match('/^([1-9][0-9]{3})\s*[–—-]\s*([1-9][0-9]{3})$/uD', $text, $groups) === 1) {
            $startDate = $groups[1];
            $endDate = $groups[2];
        } elseif (preg_match('/^([1-9][0-9]{3})$/D', $text) === 1) {
            $startDate = $text;
            $endDate = null;
        } elseif (preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December|Januar|Februar|März|Mai|Juni|Juli|Oktober|Dezember)\s+([1-9][0-9]{3})$/iuD', $text, $groups) === 1) {
            $month = match (strtolower(str_replace('Ä', 'ä', $groups[1]))) {
                'january', 'januar' => 1,
                'february', 'februar' => 2,
                'march', 'märz' => 3,
                'april' => 4,
                'may', 'mai' => 5,
                'june', 'juni' => 6,
                'july', 'juli' => 7,
                'august' => 8,
                'september' => 9,
                'october', 'oktober' => 10,
                'november' => 11,
                'december', 'dezember' => 12,
            };
            $startDate = sprintf('%04d-%02d', (int) $groups[2], $month);
            $endDate = null;
        } elseif (preg_match('/^([0-9]{2}\.[0-9]{2}\.[0-9]{4})(?:\s*([–—&])\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4}))?$/uD', $text, $groups) === 1) {
            $startDate = self::germanDay($groups[1]);
            $endDate = isset($groups[3]) ? self::germanDay($groups[3]) : null;
            if (($groups[2] ?? null) === '&' && $endDate !== (new DateTimeImmutable($startDate))->modify('+1 day')->format('Y-m-d')) {
                throw new InvalidArgumentException('Non-consecutive exhibition dates require explicit review.');
            }
        } else {
            throw new InvalidArgumentException('Unrecognized authored date expression: '.$text);
        }

        $startDate = self::normalize($startDate, 'authored start') ?? throw new InvalidArgumentException('Missing authored exhibition start date.');
        $endDate = self::normalize($endDate, 'authored end');
        if (! self::validRange($startDate, $endDate)) {
            throw new InvalidArgumentException('Authored exhibition end precedes the start.');
        }
        if (($structuredStart !== null && $structuredStart !== $startDate)
            || ($structuredEnd !== null && $structuredEnd !== $endDate)) {
            throw new InvalidArgumentException('Authored date conflicts with already stored structured dates.');
        }

        return ['starts_on' => $startDate, 'ends_on' => $endDate];
    }

    public static function format(?string $value, string $region): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = self::normalize($value) ?? throw new InvalidArgumentException('Missing exhibition date.');
        $year = substr($value, 0, 4);
        if (strlen($value) === 4) {
            return $year;
        }
        $month = substr($value, 5, 2);
        if (strlen($value) === 7) {
            return $region === 'de-DE' ? $month.'.'.$year : $month.'/'.$year;
        }

        return RegionalFormats::date(new DateTimeImmutable($value), $region);
    }

    public static function display(?string $start, ?string $end, string $region): ?string
    {
        $first = self::format($start, $region);
        if ($first === null) {
            return null;
        }
        $last = self::format($end, $region);

        return $last !== null && $last !== $first ? $first.' – '.$last : $first;
    }

    public static function validRange(?string $start, ?string $end): bool
    {
        if ($end === null) {
            return true;
        }
        if ($start === null) {
            return false;
        }

        return self::earliest($start) <= self::latest($end);
    }

    public static function temporalState(?string $start, ?string $end, DateTimeInterface $now): string
    {
        if ($start === null) {
            return 'unknown';
        }
        $today = $now->format('Y-m-d');
        if ($today < self::earliest($start)) {
            return 'upcoming';
        }
        if ($today > self::latest($end ?? $start)) {
            return 'past';
        }

        return strlen($start) === 10 && ($end === null || strlen($end) === 10)
            ? 'current' : 'unknown';
    }

    private static function earliest(string $value): string
    {
        return strlen($value) === 4
            ? $value.'-01-01'
            : (strlen($value) === 7 ? $value.'-01' : $value);
    }

    private static function latest(string $value): string
    {
        if (strlen($value) === 4) {
            return $value.'-12-31';
        }
        if (strlen($value) === 7) {
            return (new DateTimeImmutable($value.'-01'))->modify('last day of this month')->format('Y-m-d');
        }

        return $value;
    }

    private static function germanDay(string $value): string
    {
        if (preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            throw new InvalidArgumentException('Invalid German day-month-year date.');
        }

        return sprintf('%04d-%02d-%02d', (int) $parts[3], (int) $parts[2], (int) $parts[1]);
    }
}
