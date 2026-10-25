<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationResolver;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

require_once __DIR__.'/HomeHeroFixtures.php';

it('uses the same canonical Hero resolver for direct domain and public Home presentation', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $anchor = CarbonImmutable::parse('2026-08-01T00:00:00Z');
    CarbonImmutable::setTestNow($anchor->addDay());
    $settings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'manual_group' => heroManualMembers($first, $second),
        'rotation_interval' => ['count' => 1, 'unit' => 'days'],
        'rotation_started_at' => $anchor->toIso8601String(),
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $first->getKey(),
    ]);

    $domainCurrent = app(HomeHeroResolver::class)->resolve($settings, CarbonImmutable::now('UTC'))['current'];
    $publicCurrent = app(HomePresentationResolver::class)->presentation()['artwork'];

    expect($publicCurrent->getKey())->toBe($domainCurrent->getKey())
        ->and($publicCurrent->getKey())->toBe($second->getKey());
});


it('keeps the four independent Hero display options in the existing configuration and public projection', function (): void {
    $service = app(HomeHeroConfigurationService::class);
    $settings = heroDomainSettings();

    $defaults = $service->configuration($settings);
    expect($defaults['show_details'])->toBeTrue()
        ->and($defaults['show_gallery_link'])->toBeFalse()
        ->and($defaults['show_navigation'])->toBeFalse()
        ->and($defaults['enable_viewer'])->toBeTrue();

    $settings->refresh();
    $changed = $service->updateArtworkSettings($settings, [
        'show_details' => false,
        'show_gallery_link' => false,
        'show_navigation' => true,
        'enable_viewer' => false,
    ]);
    expect($changed)->toBeTrue();
    $saved = $service->configuration($settings->fresh());
    expect($saved['show_details'])->toBeFalse()
        ->and($saved['show_gallery_link'])->toBeFalse()
        ->and($saved['show_navigation'])->toBeTrue()
        ->and($saved['enable_viewer'])->toBeFalse();

    $presentation = app(HomePresentationResolver::class)->presentation();
    expect($presentation['showDetails'])->toBeFalse()
        ->and($presentation['showGalleryLink'])->toBeFalse()
        ->and($presentation['showNavigation'])->toBeTrue()
        ->and($presentation['enableViewer'])->toBeFalse();

    expect($service->updateArtworkSettings($settings->fresh(), [
        'show_details' => false,
        'show_gallery_link' => false,
        'show_navigation' => true,
        'enable_viewer' => false,
    ]))->toBeFalse();
});


it('keeps the existing Hero resolution order available for optional Home navigation', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'Hero first', 0);
    $second = heroDomainArtwork($category, 'Hero second', 1);
    $settings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'ordered',
        'manual_group' => heroManualMembers($first, $second),
        'show_navigation' => true,
    ]);

    $presentation = app(HomePresentationResolver::class)->presentation();
    expect($presentation['heroGroup']->modelKeys())->toBe([(int) $first->getKey(), (int) $second->getKey()])
        ->and($presentation['artwork']->getKey())->toBe($first->getKey())
        ->and($presentation['showNavigation'])->toBeTrue();
});
