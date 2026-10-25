<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Filament\Support\PagesSettingsDialog;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/PagesFixtures.php';

it('autosaves Pages toggles from field updates without a separate Action submit', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $sections = app(SiteSectionEditorialService::class);
    $destination = $sections->createCustomPage('Autosave Destination', 'autosave-destination');
    $sections->updatePlacement($destination, 'published', null);
    $routing = app(HomeRoutingSettingsService::class);
    $home = app(HomePresentationResolver::class)->settings();

    $component = Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeFalse()
        ->and(PublicContentSetting::navigationNestingEnabled())->toBeTrue();

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    // A real Toggle updates Livewire state; it does not submit the modal Action.
    $component->set('mountedActions.0.data.skip_home', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.navigation_nesting_enabled', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect(PublicContentSetting::navigationNestingEnabled())->toBeFalse()
        ->and($routing->enabled($home->fresh()))->toBeTrue()
        ->and($component->get('nestedNavigationEnabled'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    // A duplicate browser change/submission must be a true no-op.
    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    $component->set('mountedActions.0.data.navigation_nesting_enabled', false);
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);

    $component->call('unmountAction')->assertSet('mountedActions', []);

    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.data.skip_home', true)
        ->assertSet('mountedActions.0.data.navigation_nesting_enabled', false);
});


it('defaults Pages workflow preferences off and autosaves each new toggle once', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    expect(PublicContentSetting::navigationNestingEnabled())->toBeTrue()
        ->and(PublicContentSetting::pagesAutoPublish())->toBeFalse()
        ->and(PublicContentSetting::pagesDeleteWithoutConfirmation())->toBeFalse()
        ->and(app(PagesSettingsDialog::class)->fill()['skip_home'])->toBeFalse();

    $component = Livewire::test(SitePages::class)->mountAction('pagesSettings')
        ->assertSet('mountedActions.0.data.pages_auto_publish', false)
        ->assertSet('mountedActions.0.data.pages_delete_without_confirmation', false);

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.pages_auto_publish', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect(PublicContentSetting::pagesAutoPublish())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.pages_delete_without_confirmation', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect(PublicContentSetting::pagesDeleteWithoutConfirmation())->toBeTrue()
        ->and(PublicContentSetting::navigationNestingEnabled())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);

    $component->call('unmountAction');
    Livewire::test(SitePages::class)->mountAction('pagesSettings')
        ->assertSet('mountedActions.0.data.pages_auto_publish', true)
        ->assertSet('mountedActions.0.data.pages_delete_without_confirmation', true);

    // The shared two-column Grid keeps all four toggles in row-major
    // order; Skip Home owns its optional destination in one stable cell.
    $mounted = Livewire::test(SitePages::class)->mountAction('pagesSettings')->instance();
    $schema = $mounted->{$mounted->getMountedActionSchemaName()}->getComponents();
    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(\Filament\Schemas\Components\Grid::class);

    $cells = $schema[0]->getChildComponents();
    expect($cells)->toHaveCount(4)
        ->and($cells[1])->toBeInstanceOf(\Filament\Schemas\Components\Group::class);

    $fieldNames = [];
    foreach ($cells as $cell) {
        if ($cell instanceof \Filament\Schemas\Components\Group) {
            foreach ($cell->getChildSchema()->getComponents(withHidden: true) as $field) {
                $fieldNames[] = $field->getName();
            }
        } else {
            $fieldNames[] = $cell->getName();
        }
    }

    expect($fieldNames)->toBe([
        'navigation_nesting_enabled',
        'skip_home',
        'skip_target_section_id',
        'pages_auto_publish',
        'pages_delete_without_confirmation',
    ]);
});


it('skips Pages deletion confirmation only when configured and retains deletion safety rules', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $editor = app(SiteSectionEditorialService::class);
    $withConfirmation = $editor->createCustomPage('Confirm First', 'confirm-first');
    Livewire::test(SitePages::class)->mountAction('deletePage', ['section' => (int) $withConfirmation->getKey()])
        ->assertSet('mountedActions.0.name', 'deletePage');
    expect(SiteSection::query()->whereKey($withConfirmation->getKey())->exists())->toBeTrue();

    Livewire::test(SitePages::class)->mountAction('pagesSettings')
        ->set('mountedActions.0.data.pages_delete_without_confirmation', true)
        ->assertHasNoFormErrors();

    Livewire::test(SitePages::class)->mountAction('deletePage', ['section' => (int) $withConfirmation->getKey()])
        ->assertDispatched('admin-header-notification');
    expect(SiteSection::query()->whereKey($withConfirmation->getKey())->exists())->toBeFalse();

    $bulkA = $editor->createCustomPage('Delete A', 'delete-a');
    $bulkB = $editor->createCustomPage('Delete B', 'delete-b');
    Livewire::test(SitePages::class)
        ->set('selectedSectionIds', [(int) $bulkA->getKey(), (int) $bulkB->getKey()])
        ->mountAction('deleteSelectedPages')
        ->assertDispatched('admin-header-notification');
    expect(SiteSection::query()->whereKey($bulkA->getKey())->exists())->toBeFalse()
        ->and(SiteSection::query()->whereKey($bulkB->getKey())->exists())->toBeFalse();

    $published = $editor->createCustomPage('Protected Published', 'protected-published');
    $editor->updatePlacement($published, 'published', null);
    $blocked = Livewire::test(SitePages::class)
        ->mountAction('deletePage', ['section' => (int) $published->getKey()]);
    expect(SiteSection::query()->whereKey($published->getKey())->exists())->toBeTrue();
});


it('autosaves Skip Home destination selection and avoids duplicate mutations on no-op', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $pages = app(SiteSectionEditorialService::class);
    $first = $pages->createCustomPage('Settings Default Destination', 'settings-default-destination');
    $second = $pages->createCustomPage('Settings Selected Destination', 'settings-selected-destination');
    $pages->updatePlacement($first, 'published', null);
    $pages->updatePlacement($second, 'published', null);

    $home = app(HomePresentationResolver::class)->settings();
    $routing = app(HomeRoutingSettingsService::class);
    $component = Livewire::test(SitePages::class)->call('mountAction', 'pagesSettings')
        ->set('mountedActions.0.data.skip_home', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $component
        ->set('mountedActions.0.data.skip_target_section_id', (string) $second->getKey())
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeTrue()
        ->and($routing->configuredTargetId($home->fresh()))->toBe((int) $second->getKey());

    $auditCount = AuditEvent::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($auditCount);

    $component->call('unmountAction');
    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.data.skip_target_section_id', $second->getKey());
});


it('closing an unchanged Pages Settings dialog does not create Activity or Notifications', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);

    expect(PublicContentSetting::navigationNestingEnabled())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});

it('shows invalid Skip Home settings in the mounted form without changing preferences', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    $before = AuditEvent::query()->count();
    Livewire::test(SitePages::class)->mountAction('pagesSettings')->set('mountedActions.0.data.skip_home', true)
        ->assertHasFormErrors(['skip_target_section_id'])->assertNotDispatched('admin-header-notification');
    expect(app(HomeRoutingSettingsService::class)->enabled(app(HomePresentationResolver::class)->settings()))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('rolls back combined routing and workflow changes when a later audit fails', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $target = $service->createCustomPage('Rollback Target', 'rollback-settings-target');
    $service->updatePlacement($target, 'published', null);
    $before = AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $audit = Mockery::mock($realAudit); $calls = 0;
    $audit->shouldReceive('record')->twice()->andReturnUsing(function (...$arguments) use (&$calls, $realAudit) {
        if (++$calls === 2) { throw new RuntimeException('Audit unavailable'); }
        return $realAudit->record(...$arguments);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(\App\Domain\Admin\AdminSettingsService::class);
    app()->forgetInstance(HomeRoutingSettingsService::class);
    expect(fn () => app(PagesSettingsDialog::class)->save([
        'skip_home' => true, 'skip_target_section_id' => $target->id,
        'navigation_nesting_enabled' => true, 'pages_auto_publish' => true,
    ]))->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(app(HomeRoutingSettingsService::class)->enabled(app(HomePresentationResolver::class)->settings()->fresh()))->toBeFalse()
        ->and((bool) PublicContentSetting::query()->findOrFail(1)->pages_auto_publish)->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});
