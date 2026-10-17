<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\CustomPageWorkspace;
use App\Filament\Pages\SitePages;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/../Pages/CustomPageFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('moves a Page in an open Edit dialog and disables the reached boundary action', function (): void {
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
        ->assertSet('mountedActions.0.data.position', 2);
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

it('keeps the Custom Page component editor mounted through reorder and publication actions', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Dialog reorder', 'dialog-reorder');
    $settings->update(['blocks' => [
        ['type' => 'text', 'published' => true, 'title' => 'First', 'body' => 'One'],
        ['type' => 'text', 'published' => true, 'title' => 'Second', 'body' => 'Two'],
    ]]);

    $dialog = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 1, 'componentType' => 'text'])
        ->assertSet('mountedActions.0.name', 'editComponent');
    $dialog->mountAction('dialogMoveUp')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 0);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['Second', 'First']);
    expect($dialog->instance()->getMountedAction()->getModalAction('dialogMoveUp')->isDisabled())->toBeTrue();

    $dialog->mountAction('dialogTogglePublication')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 0);
    expect($settings->fresh()->components()[0]['published'])->toBeFalse();

    $dialog->mountAction('dialogMoveDown')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 1);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['First', 'Second']);
    expect($dialog->instance()->getMountedAction()->getModalAction('dialogMoveDown')->isDisabled())->toBeTrue();
});
