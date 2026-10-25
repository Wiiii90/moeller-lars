<?php

use App\Domain\Artwork\GalleryPresentation;
use App\Models\ArtworkCategory;

it('uses conservative defaults for unconfigured and newly migrated Galleries', function (): void {
    $gallery = new ArtworkCategory(['name' => 'Unconfigured']);
    expect(GalleryPresentation::enabled($gallery))->toBeFalse()
        ->and(GalleryPresentation::edgeMode($gallery))->toBe('disabled')
        ->and(GalleryPresentation::artworkPageSurfaceMode($gallery))->toBe('inherit')
        ->and(GalleryPresentation::artworkPageDividerOverride($gallery))->toBeNull();

    expect(GalleryPresentation::validateSettings([
        'landscape_detail_enabled' => true,
        'edge_navigation_mode' => 'hidden',
    ]))->toBe([
        'landscape_detail_enabled' => true,
        'edge_navigation_mode' => 'hidden',
    ]);

    $gallery->setAttribute('artwork_page_surface_mode', 'joined');
    expect(GalleryPresentation::artworkPageDividerOverride($gallery))->toBe('fine');
    $gallery->setAttribute('artwork_page_surface_mode', 'separate');
    expect(GalleryPresentation::artworkPageDividerOverride($gallery))->toBe('separate');
    expect(fn () => GalleryPresentation::validateSettings([
        'artwork_page_surface_mode' => 'full',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

