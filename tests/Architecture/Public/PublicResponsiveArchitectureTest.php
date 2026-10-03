<?php

it('uses the current content-fit public responsive contract', function (): void {
    $root = dirname(__DIR__, 3);
    $presentation = file_get_contents($root.'/resources/css/public-presentation.css');
    $foundation = file_get_contents($root.'/resources/css/app.css');
    $customPages = file_get_contents($root.'/resources/css/custom-pages.css');

    expect($presentation)
        ->toContain('@media (max-width: 64rem)')
        ->toContain('@media (max-width: 46rem)')
        ->toContain('@media (max-width: 34rem)')
        ->toContain('overflow-x: auto')
        ->not->toContain('max-width: 550px')
        ->not->toContain('max-width: 980px')
        ->not->toContain('max-width: 1400px');

    expect($foundation)
        ->toContain('@media (max-width: 40rem)')
        ->not->toContain('max-width: 550px');

    expect($customPages)
        ->toContain('@media (max-width: 42rem)');
});
