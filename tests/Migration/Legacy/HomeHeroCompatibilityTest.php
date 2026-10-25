<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

require_once __DIR__.'/../../Integration/Content/HomeHeroFixtures.php';

it('maps legacy fixed Hero configuration to a one-member Manual ordered group', function (): void {
    $artwork = heroDomainArtwork(heroDomainCategory(), 'Fixed');
    $settings = heroDomainSettings([
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $artwork->getKey(),
    ]);

    $configuration = app(HomeHeroConfigurationService::class)->configuration($settings);

    expect($configuration['group_source'])->toBe('manual')
        ->and($configuration['display_strategy'])->toBe('ordered')
        ->and(collect($configuration['manual_group'])->pluck('artwork_id')->all())->toBe([
            (int) $artwork->getKey(),
        ]);
});


it('maps legacy automatic newest Hero configuration to Automatic ordered', function (): void {
    $configuration = app(HomeHeroConfigurationService::class)->configuration(heroDomainSettings([
        'hero_mode' => 'automatic',
        'automatic_selection' => 'newest',
    ]));

    expect($configuration['group_source'])->toBe('automatic')
        ->and($configuration['display_strategy'])->toBe('ordered');
});


it('maps legacy random Hero configuration to Automatic random', function (): void {
    $configuration = app(HomeHeroConfigurationService::class)->configuration(heroDomainSettings([
        'hero_mode' => 'random',
    ]));

    expect($configuration['group_source'])->toBe('automatic')
        ->and($configuration['display_strategy'])->toBe('random');
});
