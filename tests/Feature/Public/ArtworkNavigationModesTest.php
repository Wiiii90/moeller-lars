<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\Blade;

it('disables unavailable artwork navigation', function (): void {
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
    // Hidden mode keeps the Gallery link, but exposes no unavailable
    // previous/next action to keyboard or assistive technology.
    $hidden = Blade::render(
        '<x-artwork-navigation :category="$gallery" :show-gallery-link="true" empty-step-mode="hidden" />',
        ['gallery' => $gallery],
    );
    expect($hidden)->toContain('Back to Drawings gallery')
        ->not->toContain('aria-label="No previous artwork"')
        ->not->toContain('aria-label="No next artwork"');
});
