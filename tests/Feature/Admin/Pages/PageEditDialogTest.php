<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
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

it('keeps duplicate slug errors on the mounted form and rolls back all earlier changes', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    $service = app(SiteSectionEditorialService::class);
    $section = $service->createCustomPage('Original', 'page-original');
    $service->createCustomPage('Reserved', 'page-reserved');
    app(AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $component = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->id])
        ->fillForm(['name' => 'Changed', 'slug' => 'page-reserved'])->callMountedAction()
        ->assertHasFormErrors(['slug'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe('Original')->and(AuditEvent::query()->count())->toBe($before)
        ->and(AdminNotification::query()->count())->toBe(0);
    $component->set('mountedActions.0.data.slug', 'page-corrected')->callMountedAction()->assertHasNoFormErrors();
    expect($section->fresh()->slug)->toBe('page-corrected')->and($section->fresh()->title)->toBe('Changed');
});

it('persists a combined rename and placement with complete audit and one notification', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    $service = app(SiteSectionEditorialService::class);
    $group = $service->createNavigationGroup('Destination');
    $section = $service->createCustomPage('Before combined', 'before-combined');
    app(AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $component = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->id])
        ->set('mountedActions.0.data.name', 'After combined')
        ->set('mountedActions.0.data.parent_id', $group->id)->set('mountedActions.0.data.position', 1)->callMountedAction()
        ->assertHasNoFormErrors()->assertHasNoErrors()->assertSee('After combined');
    expect($section->fresh()->title)->toBe('After combined')->and($section->fresh()->parent_id)->toBe($group->id)
        ->and(AuditEvent::query()->count())->toBe($before + 2)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $component->callMountedAction()->assertNotDispatched('admin-header-notification');
});

it('rejects a stale Page dialog without overwriting a concurrent identity change', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Before stale', 'before-stale');
    $component = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->id]);
    app(\App\Domain\Content\SiteSectionIdentityService::class)->update($section, 'Concurrent name', 'concurrent-slug');
    app(AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $component->set('mountedActions.0.data.name', 'Stale edit')->callMountedAction()
        ->assertHasFormErrors(['name'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe('Concurrent name')->and($section->fresh()->slug)->toBe('concurrent-slug')
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('rejects Pages Edit requests after administrative access is lost', function (bool $guest): void {
    $actor = pagesTestAdmin(); $this->actingAs($actor, 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Protected', 'protected-edit');
    $component = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->id]);
    if ($guest) { Auth::guard('web')->logout(); } else { $actor->forceFill(['is_admin' => false])->save(); }
    $component->set('mountedActions.0.data.name', 'Unauthorized')->assertForbidden();
    expect($section->fresh()->title)->toBe('Protected');
})->with(['guest' => true, 'revoked admin' => false]);


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

it('reorders Pages through the mounted editor', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $editorial = app(SiteSectionEditorialService::class);
    $first = $editorial->createCustomPage('First sibling', 'first-sibling');
    $second = $editorial->createCustomPage('Second sibling', 'second-sibling');

    $dialog = Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $second->id])
        ->assertSet('mountedActions.0.name', 'editPage');

    $dialog->mountAction('dialogMoveUp')
        ->assertHasNoErrors()
        ->assertSet('mountedActions.0.name', 'editPage')
        ->assertSet('mountedActions.0.arguments.section', $second->id)
        ->assertSet('mountedActions.0.data.position', 1);
    expect((int) $second->fresh()->position)->toBeLessThan((int) $first->fresh()->position);
    expect($dialog->instance()->getMountedAction()->getModalAction('dialogMoveUp')->isDisabled())->toBeTrue();

    $auditCount = AuditEvent::query()->count();
    $dialog->mountAction('dialogMoveUp')->assertSet('mountedActions.0.name', 'editPage');
    expect(AuditEvent::query()->count())->toBe($auditCount);

    $dialog->mountAction('toggleDialogPublication')
        ->assertSet('mountedActions.0.name', 'editPage')
        ->assertSet('mountedActions.0.arguments.section', $second->id);
    expect($second->fresh()->state)->toBe('published');
});
