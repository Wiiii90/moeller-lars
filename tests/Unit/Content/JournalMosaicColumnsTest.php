<?php

use App\Domain\Content\JournalMosaicColumns;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Support\Collection;

/** @return JournalEntryMedia */
function mosaicUsage(int $width, int $height, int $position): JournalEntryMedia
{
    $asset = new MediaAsset;
    $asset->setRawAttributes(['width' => $width, 'height' => $height]);

    $usage = new JournalEntryMedia;
    $usage->setRawAttributes(['position' => $position]);
    $usage->setRelation('mediaAsset', $asset);

    return $usage;
}

it('balances different formats without dropping images or reordering either column', function (): void {
    $items = [
        mosaicUsage(400, 260, 1),
        mosaicUsage(400, 270, 2),
        mosaicUsage(400, 700, 3),
        mosaicUsage(400, 520, 4),
    ];

    [$left, $right] = (new JournalMosaicColumns)->arrange(collect($items));

    expect($left)->toBe([$items[0], $items[2]])
        ->and($right)->toBe([$items[1], $items[3]])
        ->and(array_merge($left, $right))->toHaveCount(4);
});

it('keeps one artwork as one column and tolerates absent dimensions', function (): void {
    $usage = mosaicUsage(0, 0, 1);
    $layout = (new JournalMosaicColumns)->arrange(collect([$usage]));

    expect($layout[0])->toBe([$usage])
        ->and($layout[1])->toBe([])
        ->and((new JournalMosaicColumns)->arrange(collect()))->toBe([[], []]);
});
