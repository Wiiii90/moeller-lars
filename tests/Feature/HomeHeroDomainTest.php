<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function heroDomainSettings(array $artworkConfiguration = []): HomePresentationSetting
{
    $home = SiteSection::query()->create([
        'type' => SiteSection::TYPE_HOME,
        'title' => 'Home',
        'navigation_label' => 'Home',
        'slug' => null,
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $configuration = HomePresentationEditorialService::defaults();
    $configuration['artwork'] = array_replace($configuration['artwork'], $artworkConfiguration);

    $settings = new HomePresentationSetting;
    $settings->setAttribute('site_section_id', $home->getKey());
    $settings->setAttribute('template', 'artwork');
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

function heroDomainCategory(string $name = 'Hero source'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill([
        'slug' => 'hero-source-'.fake()->unique()->uuid(),
        'name' => $name,
        'show_on_home' => true,
    ]);
    $category->save();
    testGallerySection($category, ['state' => 'published']);

    return $category;
}

function heroDomainArtwork(ArtworkCategory $category, string $title, int $position = 0): Artwork
{
    $artwork = new Artwork;
    $artwork->fill([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'hero-artwork-'.fake()->unique()->uuid(),
        'title' => $title,
        'state' => 'published',
        'position' => $position,
        'work_year' => 2026,
        'date_precision' => 'year',
    ]);
    $artwork->save();

    return $artwork;
}

function heroManualMembers(Artwork ...$artworks): array
{
    $count = count($artworks);
    $base = intdiv(HomeHeroConfigurationService::WEIGHT_TOTAL, $count);
    $remainder = HomeHeroConfigurationService::WEIGHT_TOTAL - ($base * $count);

    return array_map(
        static fn (Artwork $artwork, int $index): array => [
            'artwork_id' => (int) $artwork->getKey(),
            'weight' => $base + ($index < $remainder ? 1 : 0),
        ],
        $artworks,
        array_keys($artworks),
    );
}

it('maps legacy fixed Hero configuration to a one-member Manual ordered group', function (): void {
    $artwork = heroDomainArtwork(heroDomainCategory(), 'Fixed');
    $settings = heroDomainSettings([
        'hero_mode' => 'fixed',
        'fixed_artwork_id' => (int) $artwork->getKey(),
    ]);

    $configuration = app(HomeHeroConfigurationService::class)->configuration($settings);

    expect($configuration['group_source'])->toBe('manual')
        ->and($configuration['display_strategy'])->toBe('ordered')
        ->and($configuration['manual_group'])->toBe([
            ['artwork_id' => (int) $artwork->getKey(), 'weight' => 10000],
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

it('adds removes and reorders Manual group members while deriving Manual count from members', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $third = heroDomainArtwork($category, 'Third', 2);
    $settings = heroDomainSettings();
    $service = app(HomeHeroConfigurationService::class);

    $service->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'display_strategy' => 'ordered',
        'manual_group' => heroManualMembers($first),
    ]);
    $service->addManualMember($settings, (int) $second->getKey());
    $service->addManualMember($settings, (int) $third->getKey());

    $configuration = $service->configuration($settings->fresh());
    expect($configuration['manual_group_count'])->toBe(3)
        ->and(array_sum(array_column($configuration['manual_group'], 'weight')))->toBe(10000);

    $service->reorderManualGroup($settings, [
        (int) $third->getKey(),
        (int) $first->getKey(),
        (int) $second->getKey(),
    ]);
    expect(array_column($service->configuration($settings->fresh())['manual_group'], 'artwork_id'))->toBe([
        (int) $third->getKey(),
        (int) $first->getKey(),
        (int) $second->getKey(),
    ]);

    $service->removeManualMember($settings, (int) $first->getKey());
    $configuration = $service->configuration($settings->fresh());
    expect($configuration['manual_group_count'])->toBe(2)
        ->and(array_sum(array_column($configuration['manual_group'], 'weight')))->toBe(10000);
});

it('rejects duplicate Manual group members and an empty active Manual group', function (): void {
    $artwork = heroDomainArtwork(heroDomainCategory(), 'Only');
    $settings = heroDomainSettings();
    $service = app(HomeHeroConfigurationService::class);
    $service->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'manual_group' => heroManualMembers($artwork),
    ]);

    expect(fn () => $service->addManualMember($settings, (int) $artwork->getKey()))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->removeManualMember($settings, (int) $artwork->getKey()))
        ->toThrow(ValidationException::class);
});

it('retains temporarily unavailable Manual members while effective resolution skips and restores them', function (): void {
    $firstCategory = heroDomainCategory('First source');
    $secondCategory = heroDomainCategory('Second source');
    $first = heroDomainArtwork($firstCategory, 'First');
    $second = heroDomainArtwork($secondCategory, 'Second');
    $settings = heroDomainSettings();
    $service = app(HomeHeroConfigurationService::class);
    $service->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'display_strategy' => 'ordered',
        'manual_group' => heroManualMembers($first, $second),
    ]);

    $secondSection = SiteSection::query()->where('artwork_category_id', $secondCategory->getKey())->firstOrFail();
    $secondSection->update(['state' => 'hidden']);

    $configuration = $service->configuration($settings->fresh());
    $resolution = app(HomeHeroResolver::class)->resolve($settings->fresh());
    expect(array_column($configuration['manual_group'], 'artwork_id'))->toContain((int) $second->getKey())
        ->and($resolution['group']->modelKeys())->toBe([(int) $first->getKey()]);

    $secondSection->update(['state' => 'published']);
    expect(app(HomeHeroResolver::class)->resolve($settings->fresh())['group']->modelKeys())->toBe([
        (int) $first->getKey(),
        (int) $second->getKey(),
    ]);
});

it('normalizes Manual weights to exactly ten thousand basis points with deterministic largest remainder rounding', function (): void {
    $service = app(HomeHeroConfigurationService::class);
    $normalized = $service->normalizeManualGroup([
        ['artwork_id' => 1, 'weight' => 100],
        ['artwork_id' => 2, 'weight' => 100],
        ['artwork_id' => 3, 'weight' => 100],
    ]);

    expect(array_column($normalized, 'weight'))->toBe([3334, 3333, 3333])
        ->and(array_sum(array_column($normalized, 'weight')))->toBe(10000);
});

it('normalizes the remaining Manual weights after an explicit percentage change', function (): void {
    $category = heroDomainCategory();
    $first = heroDomainArtwork($category, 'First', 0);
    $second = heroDomainArtwork($category, 'Second', 1);
    $third = heroDomainArtwork($category, 'Third', 2);
    $settings = heroDomainSettings();
    $service = app(HomeHeroConfigurationService::class);
    $service->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'display_strategy' => 'random',
        'manual_group' => heroManualMembers($first, $second, $third),
    ]);

    $service->updateManualMemberWeight($settings, (int) $first->getKey(), 7000);
    $weights = array_column($service->configuration($settings->fresh())['manual_group'], 'weight', 'artwork_id');

    expect($weights[(int) $first->getKey()])->toBe(7000)
        ->and(array_sum($weights))->toBe(10000)
        ->and($weights[(int) $second->getKey()] + $weights[(int) $third->getKey()])->toBe(3000);
});

it('rejects invalid Manual weights', function (): void {
    expect(fn () => app(HomeHeroConfigurationService::class)->normalizeManualGroup([
        ['artwork_id' => 1, 'weight' => -1],
    ]))->toThrow(ValidationException::class);
});

it('resolves Automatic random with equal effective weights and no persisted per-ID weights', function (): void {
    $category = heroDomainCategory();
    heroDomainArtwork($category, 'First', 0);
    heroDomainArtwork($category, 'Second', 1);
    $settings = heroDomainSettings([
        'group_source' => 'automatic',
        'display_strategy' => 'random',
        'hero_mode' => 'random',
        'group_size' => 2,
    ]);
    $resolver = app(HomeHeroResolver::class);

    $low = $resolver->resolve($settings, randomBasisPoint: 0);
    $high = $resolver->resolve($settings, randomBasisPoint: 9999);

    expect(array_sum($low['weights']))->toBe(10000)
        ->and(max($low['weights']) - min($low['weights']))->toBeLessThanOrEqual(1)
        ->and($low['current']->getKey())->toBe($low['group']->first()->getKey())
        ->and($high['current']->getKey())->toBe($high['group']->last()->getKey());
});

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
