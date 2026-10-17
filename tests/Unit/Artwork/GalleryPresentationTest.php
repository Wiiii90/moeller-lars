<?php

use App\Domain\Artwork\GalleryPresentation;
use App\Models\ArtworkCategory;

it('uses conservative defaults for unconfigured and newly migrated Galleries', function (): void {
    $gallery = new ArtworkCategory(['name' => 'Unconfigured']);
    expect(GalleryPresentation::enabled($gallery))->toBeFalse()
        ->and(GalleryPresentation::scale($gallery))->toBe(150)
        ->and(GalleryPresentation::edgeMode($gallery))->toBe('disabled');

    expect(GalleryPresentation::validateSettings([
        'landscape_detail_enabled' => true,
        'landscape_detail_scale' => '250',
        'edge_navigation_mode' => 'hidden',
    ]))->toBe([
        'landscape_detail_enabled' => true,
        'landscape_detail_scale' => 250,
        'edge_navigation_mode' => 'hidden',
    ]);
});

