<?php

namespace App\Domain\Artwork;

use InvalidArgumentException;

final class ArtworkSelectionOrder
{
    /**
     * Move every selected artwork by at most one slot while preserving selected
     * relative order. A selected item never jumps across another selected item.
     *
     * @param  list<int>  $orderedIds
     * @param  list<int>  $selectedIds
     * @return list<int>
     */
    public static function moveOneSlot(array $orderedIds, array $selectedIds, string $direction): array
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        $selected = array_fill_keys($selectedIds, true);
        if ($direction === 'up') {
            for ($index = 1; $index < count($orderedIds); $index++) {
                $current = $orderedIds[$index];
                $previous = $orderedIds[$index - 1];
                if (isset($selected[$current]) && ! isset($selected[$previous])) {
                    [$orderedIds[$index - 1], $orderedIds[$index]] = [$current, $previous];
                }
            }

            return array_values($orderedIds);
        }

        for ($index = count($orderedIds) - 2; $index >= 0; $index--) {
            $current = $orderedIds[$index];
            $next = $orderedIds[$index + 1];
            if (isset($selected[$current]) && ! isset($selected[$next])) {
                [$orderedIds[$index], $orderedIds[$index + 1]] = [$next, $current];
            }
        }

        return array_values($orderedIds);
    }
}
