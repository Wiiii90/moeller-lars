<?php

use App\Domain\Artwork\ArtworkSelectionOrder;

it('moves a multi-selection up by one slot while preserving relative order', function (): void {
    expect(ArtworkSelectionOrder::moveOneSlot([1, 2, 3, 4, 5], [3, 4], 'up'))
        ->toBe([1, 3, 4, 2, 5]);
});

it('moves a multi-selection down by one slot while preserving relative order', function (): void {
    expect(ArtworkSelectionOrder::moveOneSlot([1, 2, 3, 4, 5], [2, 3], 'down'))
        ->toBe([1, 4, 2, 3, 5]);
});

it('keeps selected edge items bounded', function (): void {
    expect(ArtworkSelectionOrder::moveOneSlot([1, 2, 3], [1, 2], 'up'))->toBe([1, 2, 3])
        ->and(ArtworkSelectionOrder::moveOneSlot([1, 2, 3], [2, 3], 'down'))->toBe([1, 2, 3]);
});
