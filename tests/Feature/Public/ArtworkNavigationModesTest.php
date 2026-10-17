<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\Blade;

it('renders Gallery end arrows as disabled controls by default', function (): void {
    $gallery = new ArtworkCategory(['name' => 'Drawings', 'slug' => 'drawings']);
    $next = new Artwork(['slug' => 'next-drawing', 'title' => 'Next drawing']);

    $html = Blade::render(
        '<x-artwork-navigation :category="$gallery" :show-gallery-link="true" :next-artwork="$next" empty-step-mode="disabled" />',
        ['gallery' => $gallery, 'next' => $next],
    );

    expect($html)->toContain('aria-label="No previous artwork"')
        ->toContain('disabled')
        ->toContain('aria-label="Next artwork"')
        ->toContain('next-drawing')
        ->not->toContain('aria-label="No next artwork"');
});

it('leaves empty non-interactive end slots for hidden Gallery navigation', function (): void {
    $gallery = new ArtworkCategory(['name' => 'Drawings', 'slug' => 'drawings']);

    $html = Blade::render(
        '<x-artwork-navigation :category="$gallery" :show-gallery-link="true" empty-step-mode="hidden" />',
        ['gallery' => $gallery],
    );

    expect($html)->toContain('artwork-folio__leading')
        ->toContain('artwork-folio__trailing')
        ->toContain('Back to Drawings gallery')
        ->not->toContain('No previous artwork')
        ->not->toContain('No next artwork')
        ->not->toContain('artwork-folio__step');
});
