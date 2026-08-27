<?php

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Content\SiteNodeRoute;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Filament\Pages\CustomPageWorkspace;
use App\Http\Controllers\PublicSiteSectionController;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Models\User;
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
function customPageWorkspaceRecord(string $title = 'Custom Page test', string $slug = 'custom-page-test'): array
{
    $section = app(SiteSectionEditorialService::class)->createCustomPage($title, $slug);

    /** @var CustomPageSetting $settings */
    $settings = CustomPageSetting::query()
        ->where('site_section_id', $section->getKey())
        ->firstOrFail();

    return [$section, $settings];
}

/** @return list<array<string,mixed>> */
function customPageWorkspaceBlocks(int $count): array
{
    $blocks = [];

    for ($index = 0; $index < $count; $index++) {
        $items = [];
        if ($index === 24) {
            for ($child = 0; $child < 4; $child++) {
                $items[] = [
                    'published' => true,
                    'date' => '202'.($child + 1),
                    'title' => 'Boundary child '.($child + 1),
                ];
            }
        } elseif ($index === $count - 1) {
            $items[] = [
                'published' => true,
                'date' => '2030',
                'title' => 'Last child',
            ];
        }

        $blocks[] = [
            'type' => 'list',
            'published' => true,
            'title' => 'Component '.($index + 1),
            'items' => $items,
        ];
    }

    return $blocks;
}

function customPageWorkspaceAsset(string $suffix): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/custom-page-workspace-'.$suffix.'.jpg',
        'original_filename' => 'custom-page-workspace-'.$suffix.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'custom-page-workspace-'.$suffix),
        'state' => 'available',
        'alt_text' => 'Custom Page workspace '.$suffix,
        'width' => 2,
        'height' => 2,
    ]);
}

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
        ->and($pageOne[24]['children'])->toHaveCount(4)
        ->and(array_column($pageOne[24]['children'], 'position'))->toBe([1, 2, 3, 4]);

    $component
        ->set('selectedComponentTargets', ['0:list'])
        ->set('selectedChildTargets', ['list:24:0'])
        ->call('nextPage')
        ->assertSet('page', 2)
        ->assertSet('selectedComponentTargets', [])
        ->assertSet('selectedChildTargets', [])
        ->assertSee('26–30 of 30');

    $pageTwo = $component->get('components');
    expect($pageTwo)->toHaveCount(5)
        ->and($pageTwo[0]['position'])->toBe(26)
        ->and($pageTwo[4]['position'])->toBe(30)
        ->and($pageTwo[4]['children'])->toHaveCount(1);

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
        ->set('selectedChildTargets', ['list:24:0', 'list:29:0'])
        ->call('setComponentPublished', 0, 'list', false)
        ->assertSet('selectedComponentTargets', ['0:list'])
        ->assertSet('selectedChildTargets', ['list:24:0']);
});

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

    $sequenceSource = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    expect($sequenceSource)->toContain('wire:sort="sortComponent"')
        ->and($sequenceSource)->toContain('wire:sort:item=')
        ->and($sequenceSource)->toContain('wire:sort:handle')
        ->and($sequenceSource)->not->toContain('draggable=')
        ->and($sequenceSource)->not->toContain('dragstart');
});

it('edits the canonical CV collection directly in the CV List dialog without duplicating entries into component JSON', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('CV editor test', 'cv-editor-test');
    $asset = customPageWorkspaceAsset('cv');
    $settings->update(['blocks' => [[
        'type' => 'cv_list',
        'published' => true,
        'media_asset_id' => null,
    ]]]);

    $first = CvEntry::query()->create([
        'section' => 'CV',
        'title' => 'First entry',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2024',
        'date_precision' => 'year',
        'body' => 'First details',
    ]);
    $second = CvEntry::query()->create([
        'section' => 'Exhibitions',
        'title' => 'Second entry',
        'state' => 'published',
        'position' => 1,
        'year_text' => '2025',
        'date_precision' => 'year',
        'body' => 'Second details',
        'image_media_asset_id' => $asset->id,
    ]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'cv_list'])
        ->assertMountedActionModalSee('First entry')
        ->assertMountedActionModalSee('Second entry')
        ->fillForm([
            'type' => 'cv_list',
            'publication_state' => 'published',
            'media_asset_id' => null,
            'cv_entries' => [
                [
                    'id' => $second->id,
                    'publication_state' => 'published',
                    'section' => 'Exhibitions',
                    'title' => 'Second entry updated',
                    'year_text' => '2026',
                    'date_precision' => 'year',
                    'starts_on' => null,
                    'ends_on' => null,
                    'organisation' => 'Museum',
                    'location' => 'Hamburg',
                    'body' => 'Updated **canonical** details',
                    'image_media_asset_id' => $asset->id,
                    'external_url' => 'https://example.com/second',
                ],
                [
                    'id' => null,
                    'publication_state' => 'unpublished',
                    'section' => 'CV',
                    'title' => 'New entry',
                    'year_text' => '2027',
                    'date_precision' => 'year',
                    'starts_on' => null,
                    'ends_on' => null,
                    'organisation' => null,
                    'location' => null,
                    'body' => 'New details',
                    'image_media_asset_id' => null,
                    'external_url' => null,
                ],
            ],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $entries = CvEntry::query()->orderBy('position')->orderBy('id')->get();
    expect($entries)->toHaveCount(2)
        ->and((int) $entries[0]->id)->toBe((int) $second->id)
        ->and($entries[0]->title)->toBe('Second entry updated')
        ->and($entries[0]->year_text)->toBe('2026')
        ->and($entries[0]->body)->toBe('Updated **canonical** details')
        ->and((int) $entries[0]->image_media_asset_id)->toBe((int) $asset->id)
        ->and($entries[0]->position)->toBe(0)
        ->and($entries[1]->title)->toBe('New entry')
        ->and($entries[1]->position)->toBe(1)
        ->and(CvEntry::query()->whereKey($first->id)->exists())->toBeFalse();

    $storedComponent = $settings->fresh()->components()[0];
    expect($storedComponent)->not->toHaveKey('cv_entries');

    $workspace = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    $children = $workspace->get('components')[0]['children'];
    expect(array_column($children, 'entry'))->toBe(['Second entry updated', 'New entry']);

    $formsSource = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceForms.php'));
    $actionsSource = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceComponentActions.php'));
    $payloadSource = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceSecondaryForms.php'));
    preg_match('/private function componentPayload\(.*?private function cvEntryPayload/s', $payloadSource, $payloadMatch);

    expect($formsSource)->toContain("Repeater::make('cv_entries')")
        ->and($formsSource)->toContain("->addActionLabel('Add CV entry')")
        ->and($formsSource)->toContain('->reorderableWithButtons()')
        ->and($formsSource)->toContain("AdminRichText::schema('body', 'Details', 10000)")
        ->and($formsSource)->toContain("MediaAssetSelect::makeId('image_media_asset_id'")
        ->and($formsSource)->not->toContain('MarkdownEditor::make')
        ->and($actionsSource)->toContain("syncCvEntryEditorRows(\$data['cv_entries'] ?? null)")
        ->and($formsSource)->toContain('app(CvEntryEditorialService::class)->syncOrdered($rows)')
        ->and($payloadMatch[0] ?? '')->not->toContain('cv_entries');
});

it('updates Custom Page identity placement and flat public path through the canonical page settings action', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $parent = $sections->createNavigationGroup('About');
    [$section] = customPageWorkspaceRecord('Original title', 'original-page-slug');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('pageSettings')
        ->fillForm([
            'title' => 'Updated title',
            'navigation_label' => 'Short label',
            'slug' => 'updated-page-slug',
            'publication_state' => 'published',
            'show_in_navigation' => true,
            'parent_id' => $parent->id,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('pageTitle', 'Updated title');

    $fresh = $section->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->navigation_label)->toBe('Short label')
        ->and($fresh->slug)->toBe('updated-page-slug')
        ->and($fresh->state)->toBe('published')
        ->and($fresh->show_in_navigation)->toBeTrue()
        ->and((int) $fresh->parent_id)->toBe((int) $parent->id)
        ->and(app(SiteNodeRoute::class)->path($fresh))->toBe('/updated-page-slug');
});

it('retains old Custom Page slugs as direct 301 redirects collapses chains and protects redirect sources from loops', function (): void {
    $service = app(SiteSectionEditorialService::class);
    [$section] = customPageWorkspaceRecord('Redirect page', 'redirect-a');
    $service->updatePlacement($section, 'published', false, null);

    $section = $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-b');
    $section = $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-c');

    $owned = Redirect::query()
        ->where('reason', SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON)
        ->orderBy('source_path')
        ->get();

    expect($owned)->toHaveCount(2)
        ->and($owned->pluck('source_path')->all())->toBe(['/redirect-a', '/redirect-b'])
        ->and($owned->pluck('target_path')->all())->toBe(['/redirect-c', '/redirect-c'])
        ->and($owned->pluck('status_code')->all())->toBe([301, 301])
        ->and($owned->contains(fn (Redirect $redirect): bool => $redirect->source_path === $redirect->target_path))->toBeFalse();

    $response = app(PublicSiteSectionController::class)->show('redirect-a');
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toEndWith('/redirect-c');

    expect(fn () => $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-a'))
        ->toThrow(ValidationException::class, 'This public URL slug is reserved or already in use.');

    expect($section->fresh()->slug)->toBe('redirect-c');
});

it('changes hierarchy placement without inventing a hierarchical Custom Page URL or redirect', function (): void {
    $service = app(SiteSectionEditorialService::class);
    $parent = $service->createNavigationGroup('Parent page');
    [$section] = customPageWorkspaceRecord('Flat route page', 'flat-route-page');
    $route = app(SiteNodeRoute::class);
    $beforePath = $route->path($section);
    $beforeRedirects = Redirect::query()->count();

    $section = $service->updatePlacement($section, 'published', true, $parent->id);

    expect($route->path($section))->toBe('/flat-route-page')
        ->and($route->path($section))->toBe($beforePath)
        ->and((int) $section->parent_id)->toBe((int) $parent->id)
        ->and(Redirect::query()->count())->toBe($beforeRedirects);
});

it('keeps the Custom Page presentation on shared editorial primitives and central editor authorities', function (): void {
    $wrapper = file_get_contents(resource_path('views/filament/pages/custom-page-workspace.blade.php'));
    $controls = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-controls.blade.php'));
    $sequence = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $footer = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-footer.blade.php'));
    $customCss = file_get_contents(resource_path('css/admin/custom-page.css'));
    $sharedCss = file_get_contents(resource_path('css/admin/editorial-primitives.css'));
    $blade = implode("\n", [$wrapper, $controls, $sequence, $footer]);

    expect($wrapper)->toContain('class="custom-page-workspace"')
        ->and($sequence)->toContain('admin-position-badge')
        ->and($sequence)->toContain('admin-action-slot')
        ->and($sequence)->toContain('admin-action is-danger')
        ->and($footer)->toContain('admin-bottom-add')
        ->and($footer)->toContain('admin-pager')
        ->and($footer)->toContain('wire:model.live.number="pageSize"')
        ->and($sharedCss)->toContain('--admin-control-text-inset:')
        ->and($sharedCss)->toContain('.admin-action.is-danger')
        ->and($sharedCss)->toContain('.admin-action-slot')
        ->and($sharedCss)->toContain('.admin-position-badge')
        ->and($sharedCss)->toContain('.admin-bottom-add')
        ->and($sharedCss)->toContain('.admin-pager')
        ->and($customCss)->toContain('padding: .3rem 1.7rem .3rem var(--admin-control-text-inset);')
        ->and($customCss)->toContain('padding-inline-start: var(--admin-control-text-inset);')
        ->and($customCss)->not->toContain('.admin-action-slot')
        ->and($customCss)->not->toContain('.admin-action.is-danger')
        ->and($customCss)->not->toContain('pager')
        ->and($blade)->not->toContain('position-digits')
        ->and($blade)->not->toContain('style="')
        ->and($blade)->not->toContain('Create the CV List first')
        ->and($blade)->not->toContain('Vita');
});
