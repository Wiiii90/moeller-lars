<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/PagesFixtures.php';

it('persists successive Pages Edit changes while preserving the mounted form and suppressing no-op notifications', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createCustomPage('Before Edit', 'before-edit');
    app(AdminActivityNotificationContext::class)->discard();
    $notificationsBefore = AdminNotification::query()->count();
    $component = Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.name', 'editPage');

    $component->set('mountedActions.0.data.name', 'After Edit')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editPage')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect($section->fresh()->title)->toBe('After Edit')
        ->and($section->fresh()->navigation_label)->toBe('After Edit');

    $auditCount = AuditEvent::query()->count();
    expect(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $component->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editPage')
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $component->call('unmountAction')->assertSet('mountedActions', []);
    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.name', 'After Edit');
});


it('rejects invalid Pages Edit inputs without partially persisting earlier fields', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createCustomPage('Validation Example', 'validation-example');
    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->set('mountedActions.0.data.name', 'Should Not Persist')
        ->set('mountedActions.0.data.slug', 'not a valid slug')
        ->call('callMountedAction')
        ->assertHasFormErrors(['slug'])
        ->assertSet('mountedActions.0.name', 'editPage');

    expect($section->fresh()->title)->toBe('Validation Example')
        ->and($section->fresh()->slug)->toBe('validation-example');
});
