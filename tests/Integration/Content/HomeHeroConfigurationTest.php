<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
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

require_once __DIR__.'/HomeHeroFixtures.php';

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

it('lets a Gallery allow Home while an individual Artwork explicitly opts out', function (): void {
    $gallery = heroDomainCategory('Home opt-out');
    $allowed = heroDomainArtwork($gallery, 'Eligible painting', 0);
    $excluded = heroDomainArtwork($gallery, 'Excluded drawing', 1);
    $settings = heroDomainSettings();
    app(HomeHeroConfigurationService::class)->updateArtworkSettings($settings, [
        'group_source' => 'manual',
        'manual_group' => heroManualMembers($allowed, $excluded),
    ]);

    // A previously chosen manual member is hidden while excluded; its
    // stored selection is retained and becomes eligible again on opt-in.
    $excluded->setAttribute('exclude_from_home', true);
    $excluded->save();

    $manual = app(HomeHeroResolver::class)->resolve($settings->fresh());
    expect($manual['group']->pluck('id')->all())->toBe([(int) $allowed->getKey()]);

    $automatic = heroDomainSettings();
    $hero = app(HomeHeroResolver::class)->resolve($automatic);
    expect($hero['group']->pluck('id')->all())->toContain($allowed->getKey())
        ->not->toContain($excluded->getKey());

    $excluded->setAttribute('exclude_from_home', false);
    $excluded->save();
    expect(app(HomeHeroResolver::class)->resolve($automatic->fresh())['group']->pluck('id')->all())
        ->toContain($allowed->getKey(), $excluded->getKey());
});
