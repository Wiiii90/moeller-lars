<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

require_once __DIR__.'/HomeHeroFixtures.php';

it('advances Sequential groups by frozen UTC day intervals and wraps', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $third = heroDomainArtwork($category, 'Third', 2);
    $anchor = CarbonImmutable::parse('2026-08-01T00:00:00Z');
    $settings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'manual_group' => heroManualMembers($first, $second, $third),
        'rotation_interval' => ['count' => 1, 'unit' => 'days'],
        'rotation_started_at' => $anchor->toIso8601String(),
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $first->getKey(),
    ]);
    $resolver = app(HomeHeroResolver::class);

    expect($resolver->resolve($settings, $anchor)['current']->getKey())->toBe($first->getKey())
        ->and($resolver->resolve($settings, $anchor->addDay())['current']->getKey())->toBe($second->getKey())
        ->and($resolver->resolve($settings, $anchor->addDays(3))['current']->getKey())->toBe($first->getKey());
});


it('supports Sequential week intervals', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $anchor = CarbonImmutable::parse('2026-08-01T00:00:00Z');
    $settings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'manual_group' => heroManualMembers($first, $second),
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $anchor->toIso8601String(),
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $first->getKey(),
    ]);

    expect(app(HomeHeroResolver::class)->resolve($settings, $anchor->addWeek())['current']->getKey())
        ->toBe($second->getKey());
});


it('re-anchors Sequential rotation when membership order or interval changes', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $settings = heroDomainSettings();
    $service = app(HomeHeroConfigurationService::class);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T18:15:00Z'));

    $service->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'manual_group' => heroManualMembers($first, $second),
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
    ]);
    $firstAnchor = $service->configuration($settings->fresh())['rotation_started_at'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T19:15:00Z'));
    $service->reorderManualGroup($settings, [(int) $second->getKey(), (int) $first->getKey()]);
    $secondAnchor = $service->configuration($settings->fresh())['rotation_started_at'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T20:15:00Z'));
    $service->updateArtworkSettings($settings, [
        'rotation_interval' => ['count' => 2, 'unit' => 'days'],
    ]);
    $thirdAnchor = $service->configuration($settings->fresh())['rotation_started_at'];

    expect($firstAnchor)->toBe('2026-08-27T18:15:00+00:00')
        ->and($secondAnchor)->toBe('2026-08-27T19:15:00+00:00')
        ->and($thirdAnchor)->toBe('2026-08-27T20:15:00+00:00');
});


it('re-anchors Automatic Sequential rotation when the candidate definition changes', function (array $initial, array $update): void {
    $oldAnchor = '2026-08-01T00:00:00+00:00';
    $settings = heroDomainSettings(array_replace([
        'group_source' => 'automatic',
        'display_strategy' => 'sequential',
        'hero_mode' => 'automatic',
        'automatic_selection' => 'newest',
        'newest_by' => 'artwork_date',
        'group_size' => 3,
        'pool_rule' => 'newest',
        'pool_year' => null,
        'manual_include_ids' => [],
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $oldAnchor,
    ], $initial));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T21:00:00Z'));

    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, $update);

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['rotation_started_at'])
        ->toBe('2026-08-27T21:00:00+00:00');
})->with([
    'group size' => [[], ['group_size' => 5]],
    'newest ordering' => [[], ['newest_by' => 'added']],
    'candidate filter' => [[], ['candidate_filter' => 'year', 'specific_year' => 2025]],
    'active specific year' => [['pool_rule' => 'year', 'pool_year' => 2025], ['specific_year' => 2026]],
]);


it('re-anchors Automatic Sequential rotation when Additional Includes change', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First');
    $second = heroDomainArtwork($category, 'Second');
    $oldAnchor = '2026-08-01T00:00:00+00:00';
    $settings = heroDomainSettings([
        'group_source' => 'automatic',
        'display_strategy' => 'sequential',
        'hero_mode' => 'automatic',
        'group_size' => 3,
        'manual_include_ids' => [(int) $first->getKey()],
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $oldAnchor,
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T21:15:00Z'));

    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, [
        'manual_include_ids' => [(int) $first->getKey(), (int) $second->getKey()],
    ]);

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['rotation_started_at'])
        ->toBe('2026-08-27T21:15:00+00:00');
});


it('keeps the Automatic Sequential anchor for semantically identical settings', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First');
    $second = heroDomainArtwork($category, 'Second');
    $oldAnchor = '2026-08-01T00:00:00+00:00';
    $settings = heroDomainSettings([
        'group_source' => 'automatic',
        'display_strategy' => 'sequential',
        'hero_mode' => 'automatic',
        'newest_by' => 'artwork_date',
        'group_size' => 3,
        'pool_rule' => 'newest',
        'pool_year' => null,
        'manual_include_ids' => [(int) $first->getKey(), (int) $second->getKey()],
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $oldAnchor,
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T21:30:00Z'));

    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, [
        'group_size' => 3,
        'newest_by' => 'artwork_date',
        'candidate_filter' => 'all',
        'specific_year' => null,
        'manual_include_ids' => [(int) $second->getKey(), (int) $first->getKey()],
    ]);

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['rotation_started_at'])
        ->toBe($oldAnchor);
});


it('ignores stale specific year changes while the Automatic candidate filter remains all', function (): void {
    $oldAnchor = '2026-08-01T00:00:00+00:00';
    $settings = heroDomainSettings([
        'group_source' => 'automatic',
        'display_strategy' => 'sequential',
        'hero_mode' => 'automatic',
        'pool_rule' => 'newest',
        'pool_year' => 2025,
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $oldAnchor,
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T21:45:00Z'));

    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, [
        'candidate_filter' => 'all',
        'specific_year' => 2026,
    ]);

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['rotation_started_at'])
        ->toBe($oldAnchor);
});


it('does not re-anchor Manual Sequential rotation for Automatic-only setting changes', function (): void {
    $artwork = heroDomainArtwork(heroDomainCategory(), 'Manual');
    $oldAnchor = '2026-08-01T00:00:00+00:00';
    $settings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $artwork->getKey(),
        'manual_group' => heroManualMembers($artwork),
        'group_size' => 3,
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => $oldAnchor,
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T22:00:00Z'));

    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, ['group_size' => 5]);

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['rotation_started_at'])
        ->toBe($oldAnchor);
});


it('keeps Manual membership and source or strategy changes as Sequential re-anchor reasons', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First');
    $second = heroDomainArtwork($category, 'Second');
    $service = app(HomeHeroConfigurationService::class);

    $manualSettings = heroDomainSettings([
        'group_source' => 'manual',
        'display_strategy' => 'sequential',
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $first->getKey(),
        'manual_group' => heroManualMembers($first),
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => '2026-08-01T00:00:00+00:00',
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T22:15:00Z'));
    $service->addManualMember($manualSettings, (int) $second->getKey());
    expect($service->configuration($manualSettings->fresh())['rotation_started_at'])
        ->toBe('2026-08-27T22:15:00+00:00');

    $sourceSettings = heroDomainSettings([
        'group_source' => 'automatic',
        'display_strategy' => 'ordered',
        'hero_mode' => 'automatic',
        'manual_group' => heroManualMembers($first),
        'rotation_interval' => ['count' => 1, 'unit' => 'weeks'],
        'rotation_started_at' => '2026-08-01T00:00:00+00:00',
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T22:30:00Z'));
    $service->updateArtworkSettings($sourceSettings, ['display_strategy' => 'sequential']);
    expect($service->configuration($sourceSettings->fresh())['rotation_started_at'])
        ->toBe('2026-08-27T22:30:00+00:00');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27T22:45:00Z'));
    $service->updateArtworkSettings($sourceSettings, ['group_source' => 'manual']);
    expect($service->configuration($sourceSettings->fresh())['rotation_started_at'])
        ->toBe('2026-08-27T22:45:00+00:00');
});
