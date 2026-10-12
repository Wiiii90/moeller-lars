<?php

it('renders empty Public sections without editorial placeholder messages', function (): void {
    $root = dirname(__DIR__, 3);

    $templates = [
        'resources/views/pages/artworks/index.blade.php',
        'resources/views/pages/exhibitions.blade.php',
        'resources/views/pages/blog/index.blade.php',
        'resources/views/pages/custom.blade.php',
        'resources/views/pages/home.blade.php',
        'resources/views/pages/home/artwork.blade.php',
        'resources/views/pages/partials/component-sequence.blade.php',
    ];

    foreach ($templates as $template) {
        $source = file_get_contents($root.'/'.$template);
        expect($source)
            ->not->toContain('public-empty-state')
            ->not->toContain('No artwork is currently available.')
            ->not->toContain('No exhibitions are currently published.')
            ->not->toContain('No artwork is available for the homepage yet.');
    }

    // Empty collections produce empty markup; they do not select an alternate
    // public rendering or suppress existing publication eligibility rules.
    expect(file_get_contents($root.'/resources/views/pages/artworks/index.blade.php'))
        ->toContain('@foreach ($artworks as $artwork)')
        ->not->toContain('@forelse');
    expect(file_get_contents($root.'/resources/views/pages/exhibitions.blade.php'))
        ->toContain('@foreach ($exhibitions as $exhibition)')
        ->not->toContain('@forelse');
});
