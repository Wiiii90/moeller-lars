<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Pages\SitePages;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function pagesRepairAdmin(): User
{
    return User::factory()->admin()->create();
}

it('uses the shared admin presentation contract instead of a Pages-local theme', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/site-pages.blade.php'));
    $row = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $taskCss = file_get_contents(resource_path('css/admin/task-surfaces.css'));
    $dataCss = file_get_contents(resource_path('css/admin/data-workspace.css'));
    $customPageCss = file_get_contents(resource_path('css/admin/custom-page.css'));
    $journalWorkspace = file_get_contents(resource_path('views/filament/pages/journal-workspace.blade.php'));

    expect($view)
        ->toContain('<x-admin.workspace title="Pages">')
        ->toContain('<x-admin.metrics :columns="6">')
        ->toContain('label="Total pages"')
        ->toContain('label="Published"')
        ->toContain('label="Unpublished"')
        ->toContain('label="Top level"')
        ->toContain('label="Child pages"')
        ->toContain('label="In navigation"')
        ->toContain('admin-task-controls admin-task-controls--pages')
        ->toContain('admin-hierarchy admin-hierarchy--pages')
        ->toContain('<x-admin.add-row')
        ->toContain('wire:click="startAddingPage"')
        ->toContain('>Add page</x-admin.add-row>')
        ->toContain('admin-pager')
        ->not->toContain('admin-bottom-add')
        ->not->toContain('<style>')
        ->not->toContain('--pages-')
        ->not->toContain('min-width: 96rem')
        ->not->toContain('data-column="navigation"')
        ->not->toContain('pages-control')
        ->not->toContain('pages-position-box')
        ->not->toContain('pages-actions');

    expect(substr_count($view, '<x-admin.metric '))->toBe(6)
        ->and($row)->toContain('admin-position')
        ->and($row)->toContain('admin-inline-select')
        ->and($row)->toContain('admin-row-actions admin-toolbar')
        ->and($row)->toContain('admin-action admin-action--state')
        ->and($row)->toContain('admin-action is-danger')
        ->and($row)->not->toContain('data-cell="navigation"')
        ->and($row)->not->toContain('toggleSectionNavigation')
        ->and($row)->not->toContain('>Add<')
        ->and($row)->not->toContain('>Remove<');

    $controlOrder = [
        strpos($view, '>SEARCH<'),
        strpos($view, '>TYPE<'),
        strpos($view, '>STATUS<'),
        strpos($view, '>FILTER<'),
        strpos($view, '>PAGES<'),
        strpos($view, '>SELECTION<'),
    ];
    expect($controlOrder)->each->not->toBeFalse();
    for ($index = 0; $index < count($controlOrder) - 1; $index++) {
        expect($controlOrder[$index])->toBeLessThan($controlOrder[$index + 1]);
    }

    expect($taskCss)
        ->toContain('.admin-hierarchy {')
        ->toContain('.admin-hierarchy__row.is-child {')
        ->toContain('.admin-hierarchy__row.is-child .admin-hierarchy__content { padding-left: 1.15rem; }')
        ->toContain('.admin-position {')
        ->toContain('.admin-action--state {')
        ->not->toContain('.admin-bottom-add')
        ->not->toContain('.admin-pager {')
        ->and($dataCss)->toContain('.admin-pager,')
        ->and($customPageCss)->toContain('.custom-page-component-sequence {')
        ->and($customPageCss)->not->toContain('.admin-position {')
        ->and($customPageCss)->not->toContain('.admin-action--state')
        ->and($customPageCss)->not->toContain('.admin-action.is-danger')
        ->and($customPageCss)->not->toContain('.admin-pager')
        ->and($journalWorkspace)->toContain('class="admin-position"')
        ->and($journalWorkspace)->toContain('<x-admin.add-row')
        ->and($journalWorkspace)->not->toContain('admin-bottom-add')
        ->and($journalWorkspace)->toContain('class="admin-pager"');
});

it('derives all six metrics from real SiteSections', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $parent = $service->createCustomPage('Metric Parent', 'metric-parent');
    $child = $service->createJournal('Metric Child', 'metric-child', JournalTemplate::Blog->value);
    $service->updatePlacement($parent, 'published', true, null);
    app(SiteSectionOrderService::class)->moveTo($child, (int) $parent->getKey(), 0);

    $metrics = Livewire::test(SitePages::class)->get('metrics');

    expect($metrics)->toBe([
        'total' => SiteSection::query()->count(),
        'published' => SiteSection::query()->where('state', 'published')->count(),
        'unpublished' => SiteSection::query()->where('state', '!=', 'published')->count(),
        'top_level' => SiteSection::query()->whereNull('parent_id')->count(),
        'children' => SiteSection::query()->whereNotNull('parent_id')->count(),
        'navigation' => SiteSection::query()->where('show_in_navigation', true)->count(),
    ]);
});

it('keeps parent context when a child matches a filter and disables reorder', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $parent = $service->createCustomPage('Paintings Context', 'paintings-context');
    $child = $service->createCustomPage('Test Child Match', 'test-child-match');
    app(SiteSectionOrderService::class)->moveTo($child, (int) $parent->getKey(), 0);

    $component = Livewire::test(SitePages::class)->set('search', 'Test Child Match');
    $groups = $component->get('sections');

    expect($groups)->toHaveCount(1)
        ->and((int) $groups[0]['id'])->toBe((int) $parent->getKey())
        ->and($groups[0]['filter_context'])->toBeTrue()
        ->and($groups[0]['children'])->toHaveCount(1)
        ->and((int) $groups[0]['children'][0]['id'])->toBe((int) $child->getKey())
        ->and(collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all())
        ->toBe([(int) $parent->getKey(), (int) $child->getKey()])
        ->and($component->get('filtersActive'))->toBeTrue()
        ->and($component->get('reorderEnabled'))->toBeFalse();
});

it('resets paging on filters and keeps root groups intact across pagination', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $parent = $service->createCustomPage('Paged Parent', 'paged-parent');
    $child = $service->createCustomPage('Paged Child', 'paged-child');
    $order->moveTo($child, (int) $parent->getKey(), 0);
    foreach (range(1, 26) as $index) {
        $service->createNavigationGroup('Paged Root '.$index);
    }

    $positionsBefore = SiteSection::query()->orderBy('id')->pluck('position', 'id')->all();
    $component = Livewire::test(SitePages::class);
    $firstPageGroups = $component->get('sections');
    $pagedParent = collect($firstPageGroups)->firstWhere('id', (int) $parent->getKey());

    expect($component->get('totalGroups'))->toBe(SiteSection::query()->whereNull('parent_id')->count())
        ->and($component->get('reorderEnabled'))->toBeFalse()
        ->and($pagedParent)->not->toBeNull()
        ->and($pagedParent['children'])->toHaveCount(1)
        ->and((int) $pagedParent['children'][0]['id'])->toBe((int) $child->getKey());

    $component->call('nextPage');
    expect($component->get('pageNumber'))->toBe(2)
        ->and(SiteSection::query()->orderBy('id')->pluck('position', 'id')->all())->toBe($positionsBefore);

    $component->set('search', 'Paged Root 26');
    expect($component->get('pageNumber'))->toBe(1)
        ->and($component->get('filtersActive'))->toBeTrue()
        ->and($component->get('reorderEnabled'))->toBeFalse();
});

it('selects only the currently visible hierarchy rows with correct mixed state', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    app(SiteSectionEditorialService::class)->createCustomPage('Alpha Selection', 'alpha-selection');
    app(SiteSectionEditorialService::class)->createCustomPage('Beta Selection', 'beta-selection');

    $component = Livewire::test(SitePages::class);
    $visibleIds = collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($visibleIds)
        ->and($component->get('allVisibleSelected'))->toBeTrue()
        ->and($component->get('selectionIndeterminate'))->toBeFalse();

    $component->set('selectedSectionIds', [$visibleIds[0]]);
    expect($component->get('allVisibleSelected'))->toBeFalse()
        ->and($component->get('selectionIndeterminate'))->toBeTrue();
});

it('keeps root reorder and child reorder canonical', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $rootA = $service->createCustomPage('Root A', 'root-a-order');
    $rootB = $service->createCustomPage('Root B', 'root-b-order');
    $childA = $service->createNavigationGroup('Child A');
    $childB = $service->createNavigationGroup('Child B');

    $order->moveTo($childA, (int) $rootA->getKey(), 0);
    $order->moveTo($childB, (int) $rootA->getKey(), 1);
    expect($order->moveTo($childB, (int) $rootA->getKey(), 0))->toBeTrue();
    expect($childB->refresh()->position)->toBe(10)
        ->and($childA->refresh()->position)->toBe(20);

    expect($order->moveTo($rootB, null, 0))->toBeTrue();
    expect($rootB->refresh()->parent_id)->toBeNull();
});

it('allows normal cross-parent moves child to top and top to child with normalized positions', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $parentA = $service->createCustomPage('Parent A', 'parent-a-repair');
    $parentB = $service->createJournal('Parent B', 'parent-b-repair', JournalTemplate::Blog->value);
    $page = $service->createNavigationGroup('Movable Group');

    expect($order->moveTo($page, (int) $parentA->getKey(), 0))->toBeTrue();
    expect($page->refresh()->parent_id)->toBe((int) $parentA->getKey())
        ->and($page->position)->toBe(10);

    expect($order->moveTo($page, (int) $parentB->getKey(), 0))->toBeTrue();
    expect($page->refresh()->parent_id)->toBe((int) $parentB->getKey())
        ->and($page->position)->toBe(10);

    expect($order->moveTo($page, null, 999))->toBeTrue();
    expect($page->refresh()->parent_id)->toBeNull();

    expect($order->moveTo($page, (int) $parentA->getKey(), 0))->toBeTrue();
    expect($page->refresh()->parent_id)->toBe((int) $parentA->getKey())
        ->and($page->position)->toBe(10);

    expect(AuditEvent::query()
        ->where('action', 'site_section.reordered')
        ->where('entity_type', 'site_section')
        ->where('entity_id', $page->getKey())
        ->count())->toBe(4);
});

it('rejects self parenting level three and nesting a page that already has children', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $parent = $service->createCustomPage('Parent', 'parent-depth-repair');
    $child = $service->createNavigationGroup('Child');
    $leaf = $service->createJournal('Leaf', 'leaf-depth-repair', JournalTemplate::Blog->value);
    $otherParent = $service->createCustomPage('Other Parent', 'other-parent-repair');

    $order->moveTo($child, (int) $parent->getKey(), 0);

    expect(fn () => $order->moveTo($leaf, (int) $child->getKey(), 0))
        ->toThrow(ValidationException::class, 'The parent must be a top-level page.')
        ->and(fn () => $order->moveTo($leaf, (int) $leaf->getKey(), 0))
        ->toThrow(ValidationException::class, 'A page cannot be its own parent.')
        ->and(fn () => $order->moveTo($parent, (int) $otherParent->getKey(), 0))
        ->toThrow(ValidationException::class, 'A page that already has child pages cannot itself become a child page.');
});

it('preserves Home publication delete and conversion guards even when Home is reparented', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Home Parent', 'home-parent-repair');

    expect($order->moveTo($home, (int) $parent->getKey(), 0))->toBeTrue();
    expect($home->refresh()->parent_id)->toBe((int) $parent->getKey())
        ->and($home->state)->toBe('published');

    expect(fn () => $service->updatePlacement($home, 'hidden', false, (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Home is always published.')
        ->and(fn () => $service->convertType($home, SiteNodeType::CustomPage->value))
        ->toThrow(ValidationException::class, 'Home cannot be converted')
        ->and(fn () => $home->refresh()->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');
});

it('initializes target configuration for safe type conversions transactionally', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Convertible Page', 'convertible-page-repair');

    $gallery = $service->convertType($page, SiteNodeType::Gallery->value);
    $galleryId = (int) $gallery->artwork_category_id;
    expect($gallery->type)->toBe(SiteNodeType::Gallery->value)
        ->and($galleryId)->toBeGreaterThan(0)
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeTrue()
        ->and($gallery->customPageSetting()->exists())->toBeFalse();

    $journal = $service->convertType($gallery, SiteNodeType::Journal->value);
    expect($journal->type)->toBe(SiteNodeType::Journal->value)
        ->and($journal->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->journalSetting()->exists())->toBeTrue()
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeFalse();

    $group = $service->convertType($journal, SiteNodeType::NavigationNode->value);
    expect($group->type)->toBe(SiteNodeType::NavigationNode->value)
        ->and($group->slug)->toBeNull()
        ->and($group->journalSetting()->exists())->toBeFalse();

    $custom = $service->convertType($group, SiteNodeType::CustomPage->value);
    expect($custom->type)->toBe(SiteNodeType::CustomPage->value)
        ->and($custom->slug)->not->toBeNull()
        ->and($custom->customPageSetting()->exists())->toBeTrue();
});

it('blocks destructive type conversion when custom page content exists', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Content Page', 'content-page-repair');
    $settings = $page->customPageSetting()->firstOrFail();
    $settings->setAttribute('blocks', [[
        'type' => 'divider',
        'published' => true,
        'variant' => 'thin',
    ]]);
    $settings->save();

    expect(fn () => $service->convertType($page, SiteNodeType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains components');
    expect($page->refresh()->type)->toBe(SiteNodeType::CustomPage->value)
        ->and($page->customPageSetting()->exists())->toBeTrue();
});

it('allows safe Journal template changes and blocks changes or conversion when entries exist', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $safe = $service->createJournal('Safe Journal', 'safe-journal-repair', JournalTemplate::Blog->value);
    $service->updateJournalTemplate($safe, JournalTemplate::Exhibitions->value);
    expect($safe->refresh()->template)->toBe(JournalTemplate::Exhibitions->value);

    $journal = $service->createJournal('Journal With Entry', 'journal-with-entry-repair', JournalTemplate::Blog->value);
    BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'journal-entry-repair',
        'title' => 'Existing entry',
        'body' => null,
        'state' => 'draft',
        'position' => 10,
    ]);

    expect(fn () => $service->updateJournalTemplate($journal, JournalTemplate::Exhibitions->value))
        ->toThrow(ValidationException::class, 'existing Journal entries')
        ->and(fn () => $service->convertType($journal, SiteNodeType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains entries');

    expect($journal->refresh()->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->type)->toBe(SiteNodeType::Journal->value);
});
