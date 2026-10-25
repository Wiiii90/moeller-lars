<?php

use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\Exhibition;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('persists independent organizer, physical venue and vernissage visibility via the shared editor', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Exhibition editorial Journal', 'exhibition-editorial-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Johannes der Täufer',
        'slug' => 'johannes-der-taeufer',
        'organizer' => 'Barfuss Galerie & Freundeskreis',
        'venue' => 'Simon-Petrus-Kirche',
        'location_text' => 'Harksheider Straße 12',
        'postal_code' => '22844',
        'city' => 'Norderstedt',
        'country' => 'Deutschland',
        'starts_on' => '2027-05-01',
        'ends_on' => '2027-06-01',
        'vernissage_at' => '2027-05-01 18:00:00',
        'show_vernissage' => false,
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $initialAudits = AuditEvent::query()->count();
    $initialNotifications = AdminNotification::query()->count();

    expect($entry->summaryAttribution())->toBe('Barfuss Galerie & Freundeskreis')
        ->and($entry->mapLocationLabel())->toContain('Simon-Petrus-Kirche')
        ->and($entry->streetAddress())->toBe('Harksheider Straße 12');

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertSet('mountedActions.0.data.organizer', 'Barfuss Galerie & Freundeskreis')
        ->assertSet('mountedActions.0.data.venue', 'Simon-Petrus-Kirche')
        ->set('mountedActions.0.data.organizer', 'Galerie XY')
        ->set('mountedActions.0.data.show_vernissage', true)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($entry->fresh()->getAttribute('organizer'))->toBe('Galerie XY')
        ->and($entry->fresh()->getAttribute('venue'))->toBe('Simon-Petrus-Kirche')
        ->and($entry->fresh()->getAttribute('show_vernissage'))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($initialAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($initialNotifications + 1);
});

it('audits field visibility through the existing exhibition action without duplicate notifications', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Visibility Journal', 'visibility-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Visibility Exhibition',
        'slug' => 'visibility-exhibition',
        'organizer' => 'Organizer One',
        'venue' => 'Venue One',
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->call('setExhibitionFieldVisibility', $entry->getKey(), 'organizer', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($entry->fresh()->publicFieldVisible('organizer'))->toBeFalse()
        ->and($entry->fresh()->publicFieldVisible('venue'))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AuditEvent::query()->latest('id')->value('action'))->toBe('exhibition.updated')
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $editor->call('setExhibitionFieldVisibility', $entry->getKey(), 'organizer', false);
    expect(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $editor->call('setExhibitionFieldVisibility', $entry->getKey(), 'venue', false)
        ->assertHasNoFormErrors();
    expect($entry->fresh()->publicFieldVisible('venue'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 2);
});
