<?php

namespace App\Domain\Content;

use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Support\Collection;

/**
 * Compose an exhibition's ordered media into two height-balanced columns.
 * Uses stored source dimensions, so the layout is complete on first paint:
 * no image-load measurements, interval, DOM moving or editor-only settings.
 */
final class JournalMosaicColumns
{
    /**
     * @param Collection<int, JournalEntryMedia> $usages In editorial order.
     * @return array{0: list<JournalEntryMedia>, 1: list<JournalEntryMedia>}
     */
    public function arrange(Collection $usages): array
    {
        $columns = [[], []];
        $estimatedHeights = [0.0, 0.0];

        foreach ($usages as $usage) {
            $side = $estimatedHeights[0] <= $estimatedHeights[1] ? 0 : 1;
            $columns[$side][] = $usage;

            $asset = $usage->mediaAsset;
            $width = $asset instanceof MediaAsset ? (int) $asset->getAttribute('width') : 0;
            $height = $asset instanceof MediaAsset ? (int) $asset->getAttribute('height') : 0;

            // Approximate the displayed height at a shared column width;
            // extreme originals are limited by the public gallery's max-height.
            $ratio = $width > 0 && $height > 0 ? $height / $width : 1.0;
            $estimatedHeights[$side] += max(0.5, min(2.0, $ratio)) + 0.08;
        }

        return $columns;
    }
}
