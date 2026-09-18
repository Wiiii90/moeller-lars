<?php

use Illuminate\Support\Facades\File;

it('keeps Laravel Debugbar development-only and fail-closed', function (): void {
    /** @var array<string, mixed> $composer */
    $composer = json_decode(File::get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $runtime = $composer['require'] ?? [];
    $development = $composer['require-dev'] ?? [];
    $dockerfile = File::get(base_path('Dockerfile'));

    expect($development)
        ->toHaveKey('fruitcake/laravel-debugbar')
        ->and($runtime)->not->toHaveKey('fruitcake/laravel-debugbar')
        ->and(config('debugbar.enabled'))->toBeFalse()
        ->and(config('debugbar.force_allow_enable'))->toBeFalse()
        ->and($dockerfile)->toContain('composer install --no-dev');
});
