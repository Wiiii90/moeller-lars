<?php

use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Content\DividerVariant;
use App\Models\ArtworkCategory;
use Illuminate\Validation\ValidationException;

it('offers precisely the four canonical public divider strokes with a neutral None default', function (): void {
    $gallery = new ArtworkCategory;
    expect(GalleryPresentation::DIVIDER_OPTIONS)->toBe(['none' => 'None', ...DividerVariant::LABELS])
        ->and(GalleryPresentation::divider($gallery))->toBe('none')
        ->and(GalleryPresentation::validateSettings(['artwork_divider' => 'dotted']))->toBe(['artwork_divider' => 'dotted']);

    foreach (DividerVariant::VALUES as $variant) {
        $gallery->setAttribute('artwork_divider', $variant);
        expect(GalleryPresentation::divider($gallery))->toBe($variant);
    }
});

it('rejects unsupported Gallery divider values without mutating a gallery', function (): void {
    foreach (['double', 'gradient', '', false, null] as $variant) {
        expect(fn () => GalleryPresentation::validateSettings(['artwork_divider' => $variant]))
            ->toThrow(ValidationException::class);
    }
});
