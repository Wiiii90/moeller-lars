<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use Illuminate\Support\Facades\Auth;
use App\Filament\Pages\SitePages;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('autosaves every content heading control without submitting the Pages Edit action', function (): void {
    $editorial = app(SiteSectionEditorialService::class);
    $section = $editorial->createJournal('Exhibitions', 'autosave-exhibitions', JournalTemplate::Exhibitions->value);

    $page = Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.content_heading_enabled', false);

    // Field-specific Livewire lifecycle persistence: NO callMountedAction.
    $page->set('mountedActions.0.data.content_heading_enabled', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editPage');
    expect($section->fresh()->content_heading_enabled)->toBeTrue();

    $page->set('mountedActions.0.data.content_heading_alignment', 'right')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_alignment)->toBe('right');

    $page->set('mountedActions.0.data.content_heading_divider', 'dotted')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_divider)->toBe('dotted');

    $page->set('mountedActions.0.data.content_heading_override', 'Current programme')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_override)->toBe('Current programme');

    // The generic modal change event is still allowed but must never persist
    // the heading a second time or emit a duplicate success notification.
    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->call('callMountedAction')->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);

    $page->call('unmountAction')->assertSet('mountedActions', []);

    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.content_heading_enabled', true)
        ->assertSet('mountedActions.0.data.content_heading_alignment', 'right')
        ->assertSet('mountedActions.0.data.content_heading_divider', 'dotted')
        ->assertSet('mountedActions.0.data.content_heading_override', 'Current programme');

    $actor = Auth::user();
    Auth::guard('web')->logout();
    $this->get('/autosave-exhibitions')
        ->assertStatus(404); // Not published; public data must still remain protected.

    $this->actingAs($actor, 'web');
    $editorial->updatePlacement($section->fresh(), 'published', null);
    Auth::guard('web')->logout();
    $this->get('/autosave-exhibitions')->assertNotFound();
    $this->actingAs($actor, 'web');
    app(PublicationService::class)->commit($actor);
    Auth::guard('web')->logout();
    $this->get('/autosave-exhibitions')
        ->assertOk()
        ->assertSee('Current programme');
});

it('autosaves Home heading controls and preserves existing settings and public display', function (): void {
    $section = app(HomePresentationResolver::class)->settings()->siteSection;
    expect($section)->toBeInstanceOf(SiteSection::class);

    $dialog = Livewire::test(SitePages::class)
        ->mountAction('editHome')
        ->assertSet('mountedActions.0.data.content_heading_enabled', false);

    $dialog->set('mountedActions.0.data.content_heading_enabled', true)
        ->assertDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_enabled)->toBeTrue();

    $dialog->set('mountedActions.0.data.content_heading_alignment', 'right')
        ->assertDispatched('admin-header-notification')
        ->set('mountedActions.0.data.content_heading_divider', 'thin')
        ->assertDispatched('admin-header-notification')
        ->set('mountedActions.0.data.content_heading_override', 'Studio')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_alignment)->toBe('right')
        ->and($section->fresh()->content_heading_divider)->toBe('thin')
        ->and($section->fresh()->content_heading_override)->toBe('Studio');

    $dialog->call('unmountAction');
    Livewire::test(SitePages::class)
        ->mountAction('editHome')
        ->assertSet('mountedActions.0.data.content_heading_enabled', true)
        ->assertSet('mountedActions.0.data.content_heading_alignment', 'right')
        ->assertSet('mountedActions.0.data.content_heading_divider', 'thin')
        ->assertSet('mountedActions.0.data.content_heading_override', 'Studio');

    $actor = Auth::user();
    Auth::guard('web')->logout();
    $this->get('/')->assertOk()->assertDontSee('Studio');
    $this->actingAs($actor, 'web');
    app(PublicationService::class)->commit($actor);
    Auth::guard('web')->logout();
    $this->get('/')
        ->assertOk()
        ->assertSee('Studio');
});

it('can hide and restore a Page heading without discarding the optional style or repeating audits', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Studio Page', 'autosave-studio');
    $page = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->getKey()])
        ->set('mountedActions.0.data.content_heading_enabled', true)
        ->set('mountedActions.0.data.content_heading_override', 'Override');

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->set('mountedActions.0.data.content_heading_override', 'Override');
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);

    $page->set('mountedActions.0.data.content_heading_enabled', false);
    expect($section->fresh()->content_heading_enabled)->toBeFalse()
        ->and($section->fresh()->content_heading_override)->toBe('Override');

    $page->call('unmountAction');
    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.content_heading_enabled', false)
        ->set('mountedActions.0.data.content_heading_enabled', true);

    expect($section->fresh()->content_heading_enabled)->toBeTrue()
        ->and($section->fresh()->content_heading_override)->toBe('Override');
});

it('keeps invalid heading values on the correct mounted field without persistence or success', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Invalid Heading', 'invalid-heading');
    $component = Livewire::test(SitePages::class)->mountAction('editPage', ['section' => $section->id])
        ->set('mountedActions.0.data.content_heading_enabled', true);
    $before = AuditEvent::query()->count(); $notifications = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.content_heading_alignment', 'unsupported')
        ->assertHasFormErrors(['content_heading_alignment'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->content_heading_alignment)->toBe('left')->and(AuditEvent::query()->count())->toBe($before)
        ->and(AdminNotification::query()->count())->toBe($notifications);
    $component->set('mountedActions.0.data.content_heading_alignment', 'right')->assertHasNoFormErrors();
    expect($section->fresh()->content_heading_alignment)->toBe('right');
});


it('creates a Custom Page with its heading authored through the shared Pages form', function (): void {
    Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->set('mountedActions.0.data.type', 'custom')
        ->set('mountedActions.0.data.name', 'About artist')
        ->set('mountedActions.0.data.slug', 'about-artist')
        ->set('mountedActions.0.data.content_heading_enabled', true)
        ->set('mountedActions.0.data.content_heading_override', 'About the artist')
        ->set('mountedActions.0.data.content_heading_alignment', 'left')
        ->set('mountedActions.0.data.content_heading_divider', 'subtle')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $section = SiteSection::query()->where('slug', 'about-artist')->firstOrFail();
    expect($section->content_heading_enabled)->toBeTrue()
        ->and($section->content_heading_override)->toBe('About the artist')
        ->and($section->content_heading_divider)->toBe('subtle');

    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.content_heading_enabled', true)
        ->assertSet('mountedActions.0.data.content_heading_override', 'About the artist');
});

it('keeps Home identity and heading in Pages Edit without changing its root route', function (): void {
    $home = app(HomePresentationResolver::class)->settings()->siteSection;
    $page = Livewire::test(SitePages::class)
        ->mountAction('editHome')
        ->assertSet('mountedActions.0.data.public_path', '/')
        ->assertSet('mountedActions.0.data.parent_page', 'Top level')
        ->assertSet('mountedActions.0.data.position', 1);

    $page->set('mountedActions.0.data.name', 'Atelier')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($home->fresh()->title)->toBe('Atelier')
        ->and($home->fresh()->slug)->toBeNull()
        ->and($home->fresh()->parent_id)->toBeNull();

    $count = AdminNotification::query()->count();
    $page->call('callMountedAction')->assertHasNoFormErrors();
    expect(AdminNotification::query()->count())->toBe($count);
});

it('rejects stale Home identity edits without overwriting another editor or notifying', function (): void {
    $home = app(HomePresentationResolver::class)->settings()->siteSection;
    $first = Livewire::test(SitePages::class)->mountAction('editHome');
    $stale = Livewire::test(SitePages::class)->mountAction('editHome');
    $first->fillForm(['name' => 'Current studio'])->callMountedAction()->assertHasNoFormErrors();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();

    $stale->fillForm(['name' => 'Stale studio'])->callMountedAction()
        ->assertHasFormErrors(['name'])->assertNotDispatched('admin-header-notification');
    expect($home->fresh()->title)->toBe('Current studio')
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe($notifications);
});
