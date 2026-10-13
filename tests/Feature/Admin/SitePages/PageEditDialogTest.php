<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Filament\Support\PagesSettingsDialog;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Models\BlogPost;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

require_once __DIR__.'/PagesFixtures.php';

it('persists successive Pages Edit changes while preserving the mounted form and suppressing no-op notifications', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createCustomPage('Before Edit', 'before-edit');
    $component = Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.name', 'editPage');

    $component->set('mountedActions.0.data.name', 'After Edit')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editPage');

    expect($section->fresh()->title)->toBe('After Edit')
        ->and($section->fresh()->navigation_label)->toBe('After Edit');

    $auditCount = AuditEvent::query()->count();
    $component->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editPage');

    expect(AuditEvent::query()->count())->toBe($auditCount);

    $component->call('unmountAction')->assertSet('mountedActions', []);
    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->assertSet('mountedActions.0.data.name', 'After Edit');
});


it('rejects invalid Pages Edit inputs without partially persisting earlier fields', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
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
