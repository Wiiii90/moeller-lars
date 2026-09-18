<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\HomeRoutingDialog;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\HomePresentationSetting;
use App\Models\User;

it('keeps Skip Home out of the content template options', function (): void {
    expect(HomeTemplate::options())
        ->toHaveKeys([
            HomeTemplate::Artwork->value,
            HomeTemplate::UnderConstruction->value,
            HomeTemplate::Custom->value,
        ]);

    expect(HomeTemplate::options())->not->toHaveKey(HomeTemplate::SkipHome->value);
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

it('uses the next published top-level page when the routing dialog keeps its automatic target', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();
    $sections = app(SiteSectionEditorialService::class);

    $target = $sections->createCustomPage('Next Page', 'next-page');
    $target = $sections->updatePlacement($target, 'published', true, null);
    app(SiteSectionOrderService::class)->moveTo($target, null, 1);

    $dialog = app(HomeRoutingDialog::class);
    expect($dialog->fill())
        ->toMatchArray([
            'skip_home' => false,
            'skip_target_section_id' => null,
        ]);

    $dialog->save([
        'skip_home' => true,
        'skip_target_section_id' => null,
    ]);

    /** @var HomePresentationSetting $fresh */
    $fresh = $settings->fresh();
    expect(app(HomeRoutingSettingsService::class)->enabled($fresh))->toBeTrue()
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($fresh))->toBeNull()
        ->and($resolver->skipTarget()?->getKey())->toBe($target->getKey());
});

it('changes an explicit Skip Home target through the routing dialog', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();
    $sections = app(SiteSectionEditorialService::class);

    $first = $sections->createCustomPage('First Target', 'first-target');
    $first = $sections->updatePlacement($first, 'published', true, null);
    $second = $sections->createCustomPage('Second Target', 'second-target');
    $second = $sections->updatePlacement($second, 'published', true, null);

    $dialog = app(HomeRoutingDialog::class);
    $dialog->save([
        'skip_home' => true,
        'skip_target_section_id' => (int) $second->getKey(),
    ]);

    /** @var HomePresentationSetting $fresh */
    $fresh = $settings->fresh();
    expect(app(HomeRoutingSettingsService::class)->configuredTargetId($fresh))->toBe((int) $second->getKey())
        ->and($resolver->skipTarget()?->getKey())->toBe($second->getKey());

    $dialog->save([
        'skip_home' => false,
        'skip_target_section_id' => (int) $first->getKey(),
    ]);

    /** @var HomePresentationSetting $disabled */
    $disabled = $settings->fresh();
    expect(app(HomeRoutingSettingsService::class)->enabled($disabled))->toBeFalse()
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($disabled))->toBeNull();
});

it('does not pin the automatic Skip Home target when the Home template changes', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();
    $sections = app(SiteSectionEditorialService::class);

    $target = $sections->createCustomPage('Automatic Target', 'automatic-target');
    $target = $sections->updatePlacement($target, 'published', true, null);
    app(SiteSectionOrderService::class)->moveTo($target, null, 1);

    app(HomeRoutingDialog::class)->save([
        'skip_home' => true,
        'skip_target_section_id' => null,
    ]);
    app(HomeSettingsDialog::class)->changeTemplate(HomeTemplate::Custom);

    /** @var HomePresentationSetting $fresh */
    $fresh = $settings->fresh();
    expect($fresh->template())->toBe(HomeTemplate::Custom)
        ->and(app(HomeRoutingSettingsService::class)->enabled($fresh))->toBeTrue()
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($fresh))->toBeNull()
        ->and($resolver->skipTarget()?->getKey())->toBe($target->getKey());
});
