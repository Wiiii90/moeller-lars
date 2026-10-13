<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Filament\Pages\CustomPageWorkspace;
use App\Http\Controllers\PublicSiteSectionController;
use App\Models\CustomPageSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Models\User;
use App\Routing\SiteNodeRoute;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    config()->set('analytics.matomo.reporting_enabled', false);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @return array{0:SiteSection,1:CustomPageSetting} */

require_once __DIR__.'/CustomPageFixtures.php';

it('allows canonical reorder only for a neutral complete parent sequence', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Reorder test', 'reorder-test');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('reorderComponents', ['2:list', '1:list', '0:list']);

    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 2',
        'Component 1',
    ]);

    $filtered = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->set('componentSearch', 'Component 2');
    $filtered->call('reorderComponents', ['1:list']);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 2',
        'Component 1',
    ]);

    [$largeSection, $largeSettings] = customPageWorkspaceRecord('Paged reorder test', 'paged-reorder-test');
    $largeSettings->update(['blocks' => customPageWorkspaceBlocks(30)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $largeSection->id])
        ->call('reorderComponents', array_map(static fn (int $index): string => $index.':list', range(24, 0)));

    expect($largeSettings->fresh()->components()[0]['title'])->toBe('Component 1');
});


it('persists native parent drag sorting through the canonical component order service', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('CV parent order', 'cv-parent-order');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('sortComponent', '2:list', 0)
        ->assertHasNoErrors();

    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 1',
        'Component 2',
    ]);
});
