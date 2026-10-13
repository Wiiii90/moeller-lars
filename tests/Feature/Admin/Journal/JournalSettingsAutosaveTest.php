<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('switches Journal template through native Edit autosave without an extra confirmation field or heading', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Editorial Journal', 'editorial-journal', JournalTemplate::Blog->value,
    );

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->assertMountedActionModalSee('Template');

    $editor->set('mountedActions.0.data.template', JournalTemplate::Exhibitions->value)
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Exhibitions->value);
});
