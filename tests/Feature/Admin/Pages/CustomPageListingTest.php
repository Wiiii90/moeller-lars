<?php

use App\Filament\Pages\CustomPageWorkspace;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    config()->set('analytics.matomo.reporting_enabled', false);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @return array{0:SiteSection,1:CustomPageSetting} */

require_once __DIR__.'/CustomPageFixtures.php';

it('paginates parent components in 25 50 and 100 unit pages without splitting children or resetting global positions', function (): void {
    [$section, $settings] = customPageWorkspaceRecord();
    $settings->update(['blocks' => customPageWorkspaceBlocks(30)]);

    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->assertSet('page', 1)
        ->assertSet('pageSize', 25)
        ->assertSet('total', 30)
        ->assertSet('pages', 2)
        ->assertSee('1–25 of 30');

    $pageOne = $component->get('components');
    expect($pageOne)->toHaveCount(25)
        ->and($pageOne[0]['position'])->toBe(1)
        ->and($pageOne[24]['position'])->toBe(25)
        ->and($pageOne[24]['children'])->toBe([]);

    $component
        ->set('selectedComponentTargets', ['0:list'])
        ->call('nextPage')
        ->assertSet('page', 2)
        ->assertSet('selectedComponentTargets', [])
        ->assertSee('26–30 of 30');

    $pageTwo = $component->get('components');
    expect($pageTwo)->toHaveCount(5)
        ->and($pageTwo[0]['position'])->toBe(26)
        ->and($pageTwo[4]['position'])->toBe(30)
        ->and($pageTwo[4]['children'])->toBe([]);

    $component
        ->set('pageSize', 50)
        ->assertSet('page', 1)
        ->assertSet('pages', 1)
        ->assertSee('1–30 of 30');
    expect($component->get('components'))->toHaveCount(30);

    $component->set('pageSize', 100)
        ->assertSet('page', 1)
        ->assertSet('pageSize', 100);
    expect($component->get('components'))->toHaveCount(30);
});


it('resets pagination for search type and page size changes and prunes invisible selections after projection', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Projection test', 'projection-test');
    $settings->update(['blocks' => customPageWorkspaceBlocks(30)]);

    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);

    $component->call('nextPage')->assertSet('page', 2);
    $component->set('componentSearch', 'Component 30')
        ->assertSet('page', 1)
        ->assertSet('total', 1);
    expect($component->get('components')[0]['position'])->toBe(30);

    $component->set('componentSearch', '')
        ->call('nextPage')
        ->assertSet('page', 2)
        ->set('componentType', 'list')
        ->assertSet('page', 1)
        ->assertSet('total', 30);

    $component->call('nextPage')->assertSet('page', 2);
    $component->set('pageSize', 50)
        ->assertSet('page', 1)
        ->assertSet('pageSize', 50);

    $component->set('pageSize', 25)
        ->set('selectedComponentTargets', ['0:list', '29:list'])
        ->call('setComponentPublished', 0, 'list', false)
        ->assertSet('selectedComponentTargets', ['0:list']);
});
