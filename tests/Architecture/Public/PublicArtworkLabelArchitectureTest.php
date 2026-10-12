<?php

it('keeps artist-authored Artwork titles intact in the shared gallery label', function (): void {
    $root = dirname(__DIR__, 3);
    $label = file_get_contents($root.'/resources/views/components/artwork-label.blade.php');
    $card = file_get_contents($root.'/resources/views/components/artwork-card.blade.php');
    $detail = file_get_contents($root.'/resources/views/pages/artworks/show.blade.php');

    expect($label)
        ->toContain('{{ trim((string) $artwork->title) }}')
        ->not->toContain('preg_match(')
        ->not->toContain('title-qualifier');

    // Home and galleries share artwork-card; the detail page uses the same label.
    expect($card)->toContain('<x-artwork-label :artwork="$artwork" />');
    expect($detail)->toContain('<x-artwork-label :artwork="$artwork" :show-description="false" />');
});
