<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\User;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('saves a Journal template change with one audit and notification, and stays silent on no-op', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Editorial Journal', 'editorial-journal', JournalTemplate::Blog->value,
    );

    $beforeAudits = AuditEvent::query()->count();
    $beforeNotifications = AdminNotification::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->assertMountedActionModalSee('Template');

    $editor->set('mountedActions.0.data.template', JournalTemplate::Exhibitions->value)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Exhibitions->value)
        ->and(AuditEvent::query()->count())->toBe($beforeAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($beforeAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);
});
