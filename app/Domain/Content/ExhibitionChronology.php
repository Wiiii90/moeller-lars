<?php

namespace App\Domain\Content;

use App\Models\Exhibition;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/** Canonical Exhibition chronology for Public, Admin and editor previews. */
final class ExhibitionChronology
{
    /** @param Collection<int, Exhibition> $entries
     *  @return Collection<int, Exhibition>
     */
    public static function order(Collection $entries, ?DateTimeInterface $today = null): Collection
    {
        $today ??= now();

        return $entries->sort(static function (Exhibition $left, Exhibition $right) use ($today): int {
            [$leftGroup, $leftDate, $direction] = self::key($left, $today);
            [$rightGroup, $rightDate] = self::key($right, $today);

            $group = $leftGroup <=> $rightGroup;
            if ($group !== 0) {
                return $group;
            }
            $date = strcmp($leftDate, $rightDate) * $direction;
            if ($date !== 0) {
                return $date;
            }

            // A draft has a temporary negative key. On equal dates place it
            // after saved rows, where its newly assigned positive ID will land.
            $leftId = (int) $left->getKey();
            $rightId = (int) $right->getKey();

            return ($leftId > 0 ? $leftId : PHP_INT_MAX)
                <=> ($rightId > 0 ? $rightId : PHP_INT_MAX);
        })->values();
    }

    /** @return array{int, string, int} group, date boundary, direction */
    private static function key(Exhibition $entry, DateTimeInterface $today): array
    {
        $start = $entry->getAttribute('starts_on');
        if (! is_string($start) || $start === '') {
            return [4, '', 1];
        }
        $savedEnd = $entry->getAttribute('ends_on');
        $end = is_string($savedEnd) && $savedEnd !== '' ? $savedEnd : $start;

        return match (ExhibitionDate::temporalState($start, $end, $today)) {
            'current' => [0, ExhibitionDate::latest($end), 1],
            // An imprecise year or month spanning today is not undated.
            'unknown' => [1, ExhibitionDate::latest($end), 1],
            'upcoming' => [2, ExhibitionDate::earliest($start), 1],
            'past' => [3, ExhibitionDate::latest($end), -1],
        };
    }
}
