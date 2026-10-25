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

it('stores Home skip routing independently from the content template', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();
    $template = $resolver->template();

    $sections = app(SiteSectionEditorialService::class);
    $target = $sections->createCustomPage('Skip Target', 'skip-target');
    $target = $sections->updatePlacement($target, 'published', null);

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
    $target = $sections->updatePlacement($target, 'published', null);
    app(SiteSectionOrderService::class)->moveTo($target, null, 1);

    $dialog = app(HomeRoutingDialog::class);
    expect($dialog->fill())
        ->toMatchArray([
            'skip_home' => false,
            'skip_target_section_id' => null,
        ]);

    expect($dialog->save([
        'skip_home' => true,
        'skip_target_section_id' => null,
    ]))->toBeTrue();

    expect($dialog->save([
        'skip_home' => true,
        'skip_target_section_id' => null,
    ]))->toBeFalse();

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
    $first = $sections->updatePlacement($first, 'published', null);
    $second = $sections->createCustomPage('Second Target', 'second-target');
    $second = $sections->updatePlacement($second, 'published', null);

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
    $target = $sections->updatePlacement($target, 'published', null);
    app(SiteSectionOrderService::class)->moveTo($target, null, 1);

    app(HomeRoutingDialog::class)->save([
        'skip_home' => true,
        'skip_target_section_id' => null,
    ]);
    expect(app(HomeSettingsDialog::class)->changeTemplate(HomeTemplate::Custom))->toBeTrue()
        ->and(app(HomeSettingsDialog::class)->changeTemplate(HomeTemplate::Custom))->toBeFalse();

    /** @var HomePresentationSetting $fresh */
    $fresh = $settings->fresh();
    expect($fresh->template())->toBe(HomeTemplate::Custom)
        ->and(app(HomeRoutingSettingsService::class)->enabled($fresh))->toBeTrue()
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($fresh))->toBeNull()
        ->and($resolver->skipTarget()?->getKey())->toBe($target->getKey());
});

it('reports a fully unchanged Home settings dialog save as a no-op', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $dialog = app(HomeSettingsDialog::class);
    $data = $dialog->fill();

    expect($dialog->save($data))->toBeFalse();
});

it('rolls back earlier Home template changes when routing validation fails', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = app(HomePresentationResolver::class)->settings();
    $original = $settings->only(['template', 'configuration', 'skip_home', 'skip_target_section_id']);
    $before = \App\Models\AuditEvent::query()->count();
    $dialog = app(HomeSettingsDialog::class);
    $data = [...$dialog->fill(), 'template' => 'custom', 'skip_home' => true];
    expect(fn () => $dialog->save($data))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($settings->fresh()->only(['template', 'configuration', 'skip_home', 'skip_target_section_id']))->toBe($original)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});

it('saves combined Home template and routing settings with two audits and one notification', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $target = app(SiteSectionEditorialService::class)->createCustomPage('Combined skip target', 'combined-skip-target');
    app(SiteSectionEditorialService::class)->updatePlacement($target, 'published', null);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = \App\Models\AuditEvent::query()->count();
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\HomePresentation::class)->mountAction('settings')
        ->fillForm(['template' => 'custom', 'skip_home' => true, 'skip_target_section_id' => $target->id])
        ->callMountedAction()->assertHasNoFormErrors();
    $settings = app(HomePresentationResolver::class)->settings();
    expect($settings->template())->toBe(HomeTemplate::Custom)
        ->and(app(HomeRoutingSettingsService::class)->configuredTargetId($settings))->toBe($target->id)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(\App\Models\AuditEvent::query()->count())->toBe($before + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('keeps Home reachable at the root when its public menu visibility is disabled', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $home = \App\Models\SiteSection::query()->where('type', 'home')->firstOrFail();
    app(SiteSectionEditorialService::class)->updateHomeNavigationVisibility($home, false);
    $home->refresh();
    $label = (string) ($home->getAttribute('navigation_label') ?: $home->getAttribute('title'));
    expect($home->getAttribute('show_in_navigation'))->toBeFalse()
        ->and(app(HomeSettingsDialog::class)->fill()['show_in_navigation'])->toBeFalse()
        ->and(app(\App\Routing\SiteNodeRoute::class)->path($home))->toBe('/')
        ->and(app(\App\Domain\Content\PublicNavigationService::class)->items()->pluck('label')->all())
            ->not->toContain($label);
});

it('preserves Home menu visibility independently of Skip Home with audited no-ops', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $editor = app(SiteSectionEditorialService::class);
    $home = \App\Models\SiteSection::query()->where('type', 'home')->firstOrFail();
    $editor->updateHomeNavigationVisibility($home, false);
    $home->refresh();
    $label = (string) ($home->getAttribute('navigation_label') ?: $home->getAttribute('title'));
    $settings = app(HomePresentationResolver::class)->settings();
    $routing = app(HomeRoutingSettingsService::class);
    $navigation = app(\App\Domain\Content\PublicNavigationService::class);
    $before = \App\Models\AuditEvent::query()->where('action', 'site_section.updated')->count();

    expect($editor->updateHomeNavigationVisibility($home, false))->toBeFalse()
        ->and(\App\Models\AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before);
    expect($editor->updateHomeNavigationVisibility($home, true))->toBeTrue()
        ->and($home->fresh()->getAttribute('show_in_navigation'))->toBeTrue()
        ->and($navigation->items()->pluck('label')->all())->toContain($label)
        ->and(\App\Models\AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 1);
    expect($editor->updateHomeNavigationVisibility($home, true))->toBeFalse()
        ->and(\App\Models\AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 1);

    $target = $editor->createCustomPage('Navigation Skip Target', 'navigation-skip-target');
    $editor->updatePlacement($target, 'published', null);
    expect($routing->update($settings, true, null))->toBeTrue()
        ->and($navigation->items()->pluck('label')->all())->not->toContain($label);
    expect($routing->update($settings, false, null))->toBeTrue()
        ->and($navigation->items()->pluck('label')->all())->toContain($label);

    expect($editor->updateHomeNavigationVisibility($home, false))->toBeTrue()
        ->and($navigation->items()->pluck('label')->all())->not->toContain($label);
});

it('saves Home menu visibility from the Pages Edit dialog with one notification', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();

    $home = \App\Models\SiteSection::query()->where('type', 'home')->firstOrFail();
    app(SiteSectionEditorialService::class)->updateHomeNavigationVisibility($home, false);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = \App\Models\AdminNotification::query()->count();

    \Livewire\Livewire::test(\App\Filament\Pages\SitePages::class)
        ->mountAction('editHome')
        ->assertMountedActionModalSee('Show in public navigation')
        ->fillForm(['show_in_navigation' => true])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($home->fresh()->getAttribute('show_in_navigation'))->toBeTrue()
        ->and(\App\Models\AdminNotification::query()->count())->toBe($before + 1);
});
