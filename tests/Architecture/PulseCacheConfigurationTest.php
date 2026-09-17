<?php

it('keeps Pulse dashboard caching isolated from serialized application cache values', function (): void {
    $pulseConfig = file_get_contents(__DIR__.'/../../config/pulse.php');
    $cacheConfig = file_get_contents(__DIR__.'/../../config/cache.php');

    expect($pulseConfig)
        ->toContain("'cache' => env('PULSE_CACHE_DRIVER', 'array'),")
        ->and($cacheConfig)
        ->toContain("'serializable_classes' => false,");
});
