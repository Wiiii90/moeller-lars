<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Filament\Pages\CustomPageWorkspace;
use App\Http\Controllers\PublicSiteSectionController;
use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
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
                    'context' => '202'.($child + 1),
                    'title' => 'Boundary child '.($child + 1),
                ];
            }
        } elseif ($index === $count - 1) {
            $items[] = [
                'published' => true,
                'context' => '2030',
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

it('edits and reorders List entries only through the owning component dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('CV list order', 'cv-list-order');
    $settings->update(['blocks' => [[
        'type' => 'list', 'published' => true, 'title' => 'CV',
        'items' => [
            ['published' => true, 'title' => 'First entry', 'context' => '2022'],
            ['published' => true, 'title' => 'Second entry', 'context' => '2023'],
            ['published' => true, 'title' => 'Third entry', 'context' => '2024'],
        ],
    ]]]);

    $page = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($page->get('components')[0]['children'])->toBe([]);
    $page->assertDontSee('Add list item')
        ->mountAction('editListComponent', ['componentIndex' => 0, 'componentType' => 'list'])
        ->assertMountedActionModalSee('List entries')
        ->fillForm([
            'type' => 'list',
            'publication_state' => 'published',
            'title' => 'CV',
            'items' => [
                ['published' => true, 'title' => 'Third entry', 'context' => '2024'],
                ['published' => true, 'title' => 'First entry', 'context' => '2022'],
                ['published' => true, 'title' => 'Second entry', 'context' => '2023'],
            ],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(array_column($settings->fresh()->components()[0]['items'], 'title'))->toBe([
        'Third entry', 'First entry', 'Second entry',
    ]);
});

it('keeps List entries out of row drag handles and child selection', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Single CV entry', 'single-cv-entry');
    $settings->update(['blocks' => [[
        'type' => 'list', 'published' => true, 'title' => 'CV',
        'items' => [['published' => true, 'title' => 'Only entry', 'context' => '2024']],
    ]]]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->assertDontSeeHtml('wire:sort="sortComponent"')
        ->assertDontSeeHtml('wire:sort="sortChild"')
        ->assertDontSee('Add list item')
        ->assertSee('At least two components are needed to reorder');
});

it('persists List entry details and publication through the component Edit dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('List editor test', 'list-editor-test');
    $settings->update(['blocks' => [[
        'type' => 'list', 'published' => true, 'title' => 'CV', 'media_asset_id' => null,
        'items' => [[
            'published' => true, 'context' => '2024', 'title' => 'First entry',
            'meta' => 'Old context', 'location' => 'Berlin', 'url' => null,
            'body' => 'First details',
        ]],
    ]]]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editListComponent', ['componentIndex' => 0, 'componentType' => 'list'])
        ->fillForm([
            'type' => 'list',
            'publication_state' => 'published',
            'title' => 'CV',
            'items' => [[
                'published' => false, 'context' => 'Museum · 2026', 'title' => 'First entry updated',
                'body' => 'Updated details',
            ]],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $item = $settings->fresh()->components()[0]['items'][0];
    expect($item['published'])->toBeFalse()
        ->and($item['context'])->toBe('Museum · 2026')
        ->and($item['title'])->toBe('First entry updated')
        ->and($item['body'])->toBe('Updated details')
        ->and($item)->not->toHaveKeys(['date', 'meta', 'location', 'url']);

    $workspace = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($workspace->get('components')[0]['children'])->toBe([]);
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
        ->and((int) $fresh->parent_id)->toBe((int) $parent->id)
        ->and(app(SiteNodeRoute::class)->path($fresh))->toBe('/updated-page-slug');
});

it('hides Custom Page parent settings when global nesting is disabled and rejects a stale parent', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $parent = $sections->createNavigationGroup('Parent');
    [$section] = customPageWorkspaceRecord('No nesting', 'no-nesting');

    PublicContentSetting::general()->update(['navigation_nesting_enabled' => false]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('pageSettings')
        ->assertDontSee('Navigation parent')
        ->set('mountedActions.0.data.title', 'Renamed without nesting')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect($section->fresh()->getAttribute('title'))->toBe('Renamed without nesting')
        ->and($section->fresh()->getAttribute('parent_id'))->toBeNull();

    expect(fn () => $sections->updatePlacement($section->fresh(), 'hidden', (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Enable nested pages in Pages settings');
});

it('retains old Custom Page slugs as direct 301 redirects collapses chains and protects redirect sources from loops', function (): void {
    $service = app(SiteSectionEditorialService::class);
    [$section] = customPageWorkspaceRecord('Redirect page', 'redirect-a');
    $service->updatePlacement($section, 'published', null);

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

    $section = $service->updatePlacement($section, 'published', $parent->id);

    expect($route->path($section))->toBe('/flat-route-page')
        ->and($route->path($section))->toBe($beforePath)
        ->and((int) $section->parent_id)->toBe((int) $parent->id)
        ->and(Redirect::query()->count())->toBe($beforeRedirects);
});


it('preserves the editable Component column and distinct Content at every responsive state', function (): void {
    $sequence = file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $responsive = file_get_contents(resource_path('css/admin/responsive.css'));
    $projection = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspacePresentationHelpers.php'));

    expect($sequence)
        ->toContain('class="custom-page-component__type"')
        ->toContain('class="admin-inline-select custom-page-component__type-select"')
        ->toContain("wire:change=\"mountAction('changeComponentType'")
        ->not->toContain('@disabled(! $reorderEnabled)')
        ->not->toContain('<small class="admin-responsive-meta">{{ $pageComponent[\'type_label\'] }}</small>')
        ->not->toContain('<small class="admin-responsive-meta">{{ $childKindLabel }}</small>')
        ->and($responsive)
        ->not->toContain(".custom-page-component__type,\n")
        ->not->toContain(".custom-page-component-sequence .admin-responsive-meta")
        ->toContain('var(--admin-hierarchy-component-column)')
        ->and($projection)
        ->toContain("'primary' => \$title !== '' ? \$title : 'Contact'")
        ->toContain("'secondary' => \$enabled === [] ? 'No contact items enabled' : implode(' · ', \$enabled)");
});

it('keeps Change type reachable when filtering makes drag sorting unavailable', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Filtered type conversion', 'filtered-type-conversion');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->set('componentSearch', 'Component 2')
        ->assertSeeHtml('class="admin-inline-select custom-page-component__type-select"')
        ->assertDontSeeHtml('wire:sort="sortComponent"')
        ->mountAction('changeComponentType', [
            'componentIndex' => 1,
            'componentType' => 'list',
            'targetType' => 'divider',
        ])
        ->callMountedAction()
        ->assertHasNoErrors();

    $blocks = $settings->fresh()->components();
    expect($blocks[1]['type'])->toBe('divider')
        ->and($blocks[0]['type'])->toBe('list')
        ->and($blocks[2]['type'])->toBe('list');
});

it('persists consecutive component edits without closing the autosaving dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Edit component', 'edit-component');
    $settings->update(['blocks' => [[
        'type' => 'text',
        'published' => true,
        'title' => 'Original heading',
        'body' => 'Original content',
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->fillForm([
            'publication_state' => 'unpublished',
            'title' => 'Revised heading',
            'body' => 'Revised content',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertMountedActionModalSee('Edit component');

    $first = $settings->fresh()->components()[0];
    expect($first['published'])->toBeFalse()
        ->and($first['title'])->toBe('Revised heading')
        ->and($first['body'])->toBe('Revised content');

    $editor->fillForm([
        'publication_state' => 'unpublished',
        'title' => 'Revised heading',
        'body' => 'Later rich text',
    ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertMountedActionModalSee('Edit component');

    expect($settings->fresh()->components()[0]['body'])->toBe('Later rich text');
});

it('opens and saves the third Rich Text component from its absolute row target', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Targeted editor', 'targeted-editor');
    $settings->update(['blocks' => [
        ['type' => 'text', 'published' => true, 'title' => null, 'body' => 'First text'],
        ['type' => 'divider', 'published' => true, 'variant' => 'dotted'],
        ['type' => 'text', 'published' => true, 'title' => null, 'body' => 'Third text'],
    ]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 2, 'componentType' => 'text'])
        ->assertMountedActionModalSee('Edit component')
        ->fillForm([
            'type' => 'text',
            'publication_state' => 'published',
            'title' => 'Third heading',
            'body' => 'Updated third text',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertMountedActionModalSee('Edit component');

    $components = $settings->fresh()->components();
    expect($components)->toHaveCount(3)
        ->and($components[0]['body'])->toBe('First text')
        ->and($components[1]['type'])->toBe('divider')
        ->and($components[1]['variant'])->toBe('dotted')
        ->and($components[2]['title'])->toBe('Third heading')
        ->and($components[2]['body'])->toBe('Updated third text');
});

it('edits the Contact heading and all Contact settings in the owning component without child rows', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Contact editing', 'contact-editing');
    $settings->update(['blocks' => [[
        'type' => 'contact',
        'published' => true,
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => []],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled'],
        ],
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->assertDontSee('Contact item')
        ->assertDontSeeHtml('class="custom-page-child-row')
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Get in touch',
            'contact_email_enabled' => false,
            'contact_social_enabled' => true,
            'contact_social_platforms' => [],
            'contact_form_enabled' => true,
            'contact_form_state' => 'under_construction',
            'contact_status_text' => 'Coming soon',
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $contact = $settings->fresh()->components()[0];
    $children = collect($settings->fresh()->contactChildren($contact))->keyBy('type');
    expect($contact['title'])->toBe('Get in touch')
        ->and($children['public_email']['published'])->toBeFalse()
        ->and($children['social_links']['published'])->toBeTrue()
        ->and($children['contact_form']['published'])->toBeTrue()
        ->and($children['contact_form']['form_state'])->toBe('under_construction')
        ->and($children['contact_form']['status_text'])->toBe('Coming soon');

    $preview = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()]);
    expect($preview->get('components')[0]['content'])->toMatchArray([
        'primary' => 'Get in touch',
        'secondary' => 'Social Media Links · Contact Form',
        'meta' => '',
    ]);
    $preview->assertDontSeeHtml('custom-page-child-row');
});

it('preserves legacy List metadata as Context and links in Details without duplicate conversions', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Historical list', 'historical-list');
    $settings->update(['blocks' => [[
        'type' => 'list', 'title' => 'CV',
        'items' => [[
            'title' => 'Exhibition', 'date' => '2024', 'meta' => 'Museum', 'location' => 'Hamburg',
            'url' => 'https://example.com/details', 'body' => 'Original details',
        ]],
    ]]]);
    $item = $settings->fresh()->components()[0]['items'][0];
    expect($item['context'])->toBe('2024 · Museum · Hamburg')
        ->and($item['body'])->toContain('Original details')
        ->toContain('[More information](https://example.com/details)')
        ->and($item)->not->toHaveKeys(['date', 'meta', 'location', 'url']);

    $settings->update(['blocks' => $settings->fresh()->components()]);
    $again = $settings->fresh()->components()[0]['items'][0];
    expect(substr_count($again['body'], '[More information]'))->toBe(1);
});
 
it('retains disabled Contact settings for later re-enabling', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Hidden Contact settings', 'hidden-contact-settings');
    $settings->update(['blocks' => [[
        'type' => 'contact',
        'title' => 'Reach us',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'under_construction', 'status_text' => 'Soon'],
        ],
    ]]]);
    // A hidden form field must not reset previously authored child settings.
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Reach us',
            'contact_email_enabled' => true,
            'contact_social_enabled' => false,
            'contact_form_enabled' => false,
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['social_links']['published'])->toBeFalse()
        ->and($children['social_links']['social_platforms'])->toBe(['instagram'])
        ->and($children['contact_form']['published'])->toBeFalse()
        ->and($children['contact_form']['form_state'])->toBe('under_construction')
        ->and($children['contact_form']['status_text'])->toBe('Soon');
});

it('creates Contact with all three options enabled by default and an editable heading', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Default Contact', 'default-contact');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('addComponent')
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Contact Lars',
            'contact_email_enabled' => true,
            'contact_social_enabled' => true,
            'contact_social_platforms' => [],
            'contact_form_enabled' => true,
            'contact_form_state' => 'enabled',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $contact = $settings->fresh()->components()[0];
    expect($contact['title'])->toBe('Contact Lars')
        ->and(array_column($contact['children'], 'type'))->toBe(['public_email', 'social_links', 'contact_form'])
        ->and(array_column($contact['children'], 'published'))->toBe([true, true, true]);

    $workspace = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($workspace->get('components')[0]['content'])->toMatchArray([
        'primary' => 'Contact Lars',
        'secondary' => 'Public Email · Social Media Links · Contact Form',
        'meta' => '',
    ]);
});

it('edits image size in the owning component and keeps legacy 100% implicit', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image sizing', 'image-sizing');
    $image = [
        'type' => 'image',
        'published' => false,
        'media_asset_id' => null,
        'image_decorative' => false,
    ];
    $settings->update(['blocks' => [$image]]);
    expect($settings->fresh()->components()[0])->not->toHaveKey('image_scale_percent');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertMountedActionModalSee('Image size')
        ->assertFormSet(['image_scale_percent' => CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT]);

    $service = app(\App\Domain\Content\CustomPageEditorialService::class);
    expect($service->updateBlock($settings, 0, 'image', $image))->toBeFalse();

    $scaled = [...$image, 'image_scale_percent' => 150];
    expect($service->updateBlock($settings, 0, 'image', $scaled))->toBeTrue();
    expect($settings->fresh()->components()[0]['image_scale_percent'])->toBe(150);
    expect(CustomPageSetting::imageScalePercent($settings->fresh()->components()[0]))->toBe(150);

    expect($service->updateBlock($settings, 0, 'image', $scaled))->toBeFalse();
    expect($service->updateBlock($settings, 0, 'image', $image))->toBeTrue();
    expect($settings->fresh()->components()[0])->not->toHaveKey('image_scale_percent');
    expect(CustomPageSetting::imageScalePercent($settings->fresh()->components()[0]))->toBe(100);
});

it('rejects out-of-range and noninteger image sizes while keeping image aspect ratio styling', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image sizing bounds', 'image-sizing-bounds');
    $image = ['type' => 'image', 'published' => false, 'media_asset_id' => null, 'image_decorative' => false];

    foreach ([24, 201, '80.5', 'invalid'] as $invalid) {
        expect(fn () => $settings->update(['blocks' => [[...$image, 'image_scale_percent' => $invalid]]]))
            ->toThrow(ValidationException::class);
    }

    $settings->update(['blocks' => [[...$image, 'image_scale_percent' => '175']]]);
    expect($settings->fresh()->components()[0]['image_scale_percent'])->toBe(175);

    $view = file_get_contents(resource_path('views/pages/partials/component-sequence.blade.php'));
    $css = file_get_contents(resource_path('css/custom-pages.css'));
    expect($view)->toContain('CustomPageSetting::imageScalePercent($block)')
        ->and($css)->toContain('var(--custom-page-image-width, 28rem)')
        ->toContain('var(--custom-page-image-width-narrow, 20rem)')
        ->toContain('height: auto');
});

it('renders a scaled image and derives move permissions after filtering and pagination', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image projection', 'image-projection');
    $settings->update(['blocks' => [
        [
            'type' => 'image',
            'published' => false,
            'media_asset_id' => null,
            'image_decorative' => false,
            'image_scale_percent' => 150,
        ],
        ['type' => 'text', 'published' => false, 'title' => 'Second component', 'body' => 'Text'],
        ['type' => 'divider', 'published' => false, 'variant' => 'thin'],
    ]]);

    // Exercise the real GET that previously threw before the editor could open.
    $this->get('/admin/pages/custom/'.$section->getKey())->assertOk();

    $page = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->assertSet('total', 3);

    $rows = $page->get('components');
    expect($rows)->toHaveCount(3)
        ->and($rows[0]['can_move_up'])->toBeFalse()
        ->and($rows[0]['can_move_down'])->toBeTrue()
        ->and($rows[1]['can_move_up'])->toBeTrue()
        ->and($rows[1]['can_move_down'])->toBeTrue()
        ->and($rows[2]['can_move_up'])->toBeTrue()
        ->and($rows[2]['can_move_down'])->toBeFalse();

    $page->set('componentType', 'image')->assertSet('total', 1);
    $filtered = $page->get('components');
    expect($filtered[0]['can_move_up'])->toBeFalse()
        ->and($filtered[0]['can_move_down'])->toBeFalse();

    $page->set('componentType', 'any')
        ->set('componentSearch', 'Second component')
        ->assertSet('total', 1);
    $searched = $page->get('components');
    expect($searched[0]['can_move_up'])->toBeFalse()
        ->and($searched[0]['can_move_down'])->toBeFalse();
});


it('autosaves an image selection without closing the editor and notifies once per real change', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image replacement', 'image-replacement');

    $createImage = static fn (string $filename): \App\Models\MediaAsset => \App\Models\MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', $filename),
        'state' => 'available',
        'alt_text' => 'Artist image',
    ]);
    $first = $createImage('first-image.jpg');
    $replacement = $createImage('replacement-image.jpg');
    $settings->update(['blocks' => [[
        'type' => 'image',
        'published' => true,
        'media_asset_id' => $first->getKey(),
        'image_decorative' => false,
        'image_scale_percent' => 50,
    ]]]);

    $notificationsBefore = \App\Models\AdminNotification::query()->count();
    $auditsBefore = \App\Models\AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', $section->getKey())
        ->count();

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertFormSet(['media_asset_id' => $first->getKey(), 'image_scale_percent' => 50])
        ->set('mountedActions.0.data.media_asset_id', (string) $replacement->getKey())
        ->assertHasNoFormErrors()
        ->assertMountedActionModalSee('Edit component')
        ->assertDispatched('admin-header-notification');

    $updated = $settings->fresh()->components()[0];
    expect($updated['media_asset_id'])->toBe((int) $replacement->getKey())
        ->and($updated['image_scale_percent'])->toBe(50)
        ->and($updated['image_decorative'])->toBeFalse()
        ->and(\App\Models\AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(\App\Models\AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', $section->getKey())
            ->count())->toBe($auditsBefore + 1);

    // A second form-level autosave must not duplicate the persisted mutation.
    $editor->call('callMountedAction')->assertHasNoFormErrors();
    expect(\App\Models\AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertFormSet(['media_asset_id' => $replacement->getKey(), 'image_scale_percent' => 50]);
});

it('moves the existing CV List under a decorative Image from its Edit dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Nested CV', 'nested-cv-editor');
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/nested-cv-editor.jpg',
        'original_filename' => 'nested-cv-editor.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'nested-cv-editor'),
        'state' => 'available',
        'alt_text' => 'Portrait',
        'width' => 2,
        'height' => 2,
    ]);
    $settings->update(['blocks' => [
        ['type' => 'image', 'image_decorative' => true,
            'media_asset_id' => $asset->getKey()],
        ['type' => 'list', 'title' => 'Curriculum Vitae', 'items' => [
            ['title' => 'Born in Hamburg', 'context' => '1989'],
            ['title' => 'Lives and works in Hamburg', 'context' => null],
        ]],
    ]]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertSet('mountedActions.0.data.image_children', [])
        ->set('mountedActions.0.data.adopt_component_index', 1)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $blocks = $settings->fresh()->components();
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['children'][0]['type'])->toBe('list')
        ->and($blocks[0]['children'][0]['items'][1]['context'])->toBeNull()
        ->and($blocks[0]['children'][0]['items'][1]['title'])->toBe('Lives and works in Hamburg');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertSet('mountedActions.0.data.image_children.0.type', 'list')
        ->assertSet('mountedActions.0.data.image_children.0.items.1.title', 'Lives and works in Hamburg');
});
