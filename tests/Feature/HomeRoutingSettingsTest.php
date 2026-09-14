<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\HomePresentationSetting;
use App\Models\User;

it('keeps Skip Home out of the content template options', function (): void {
    expect(HomeTemplate::options())
        ->toHaveKeys([
            HomeTemplate::Artwork->value,
            HomeTemplate::UnderConstruction->value,
            HomeTemplate::Custom->value,
        ])
        ->not->toHaveKey(HomeTemplate::SkipHome->value);
});

it('stores Home skip routing independently from the content template', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();
    $template = $resolver->template();

    $sections = app(SiteSectionEditorialService::class);
    $target = $sections->createCustomPage('Skip Target', 'skip-target');
    $target = $sections->updatePlacement($target, 'published', true, null);

    app(HomeRoutingSettingsService::class)->update($settings, true, (int) $target->getKey());

    /** @var HomePresentationSetting $fresh */
    $fresh = $settings->fresh();
    expect($fresh->template())->toBe($template)
        ->and(app(HomeRoutingSettingsService::class)->enabled($fresh))->toBeTrue()
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($fresh))->toBe((int) $target->getKey())
        ->and($resolver->skipTarget()?->getKey())->toBe($target->getKey());
});

it('reads legacy Skip Home rows as Hero Artwork plus enabled routing', function (): void {
    $settings = app(HomePresentationResolver::class)->settings();
    $settings->setAttribute('template', HomeTemplate::SkipHome->value);
    $settings->save();

    expect(app(HomePresentationResolver::class)->template())->toBe(HomeTemplate::Artwork)
        ->and(app(HomePresentationResolver::class)->skipEnabled())->toBeTrue();
});
