<?php

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Pages\SitePages;
use App\Models\ArtworkCategory;
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

it('keeps the Pages browser on one flat editorial table contract', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/site-pages.blade.php'));
    $row = file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $component = file_get_contents(app_path('Filament/Pages/SitePages.php'));
    $source = $view."\n".$row."\n".$component;

    $columns = [
        'data-column="selection"',
        'data-column="drag"',
        'data-column="position"',
        'data-column="page-type"',
        'data-column="page"',
        'data-column="template"',
        'data-column="status"',
        'data-column="navigation"',
        'data-column="actions"',
    ];
    $positions = [];
    foreach ($columns as $column) {
        $position = strpos($view, $column);
        expect($position)->not->toBeFalse();
        $positions[] = $position;
    }
    foreach (array_keys($positions) as $index) {
        if ($index === array_key_last($positions)) {
            continue;
        }
        expect($positions[$index])->toBeLessThan($positions[$index + 1]);
    }

    $controlsMatch = preg_match('/\.pages-controls\s*\{([^}]*)\}/s', $view, $controlsRules);
    $headerMatch = preg_match('/\.pages-table__header\s*\{([^}]*)\}/s', $view, $headerRules);

    expect($controlsMatch)->toBe(1)
        ->and($headerMatch)->toBe(1)
        ->and($controlsRules[1])->not->toContain('border-bottom')
        ->and($headerRules[1])->toContain('border-bottom: 1px solid var(--pages-border);')
        ->and(substr_count($view, 'role="table"'))->toBe(1)
        ->and(substr_count($view, 'role="columnheader"'))->toBe(9)
        ->and($source)->toContain('wire:model.live.debounce.300ms="search"')
        ->and($source)->toContain('wire:model.live="typeFilter"')
        ->and($source)->toContain('wire:model.live="statusFilter"')
        ->and($source)->toContain('toggleSelectAll')
        ->and($source)->toContain('indeterminate')
        ->and($source)->toContain('bulkPublish')
        ->and($source)->toContain('bulkUnpublish')
        ->and($source)->toContain('bulkDelete')
        ->and($source)->toContain('wire:sort="sortSection"')
        ->and($source)->toContain('wire:sort:item')
        ->and($source)->toContain('wire:sort:handle')
        ->and($source)->toContain('wire:sort:group-id')
        ->and($component)->toContain('if ($this->filtersActive)')
        ->and($row)->toContain('Under {{ $section[\'parent_label\'] }}')
        ->and($view)->toContain('pages-position-box')
        ->and($view)->toContain('width: 3.35rem')
        ->and($view)->toContain('pages-actions')
        ->and($view)->toContain('grid-template-columns: 4.5rem 6.75rem 5.75rem 2.25rem 2.25rem 4.75rem')
        ->and($view)->toContain('pages-add-plus')
        ->and($view)->toContain('Add page')
        ->and($source)->not->toContain('admin-site-tree')
        ->and($source)->not->toContain('admin-list__eyebrow')
        ->and($source)->not->toContain('draggable=')
        ->and($source)->not->toContain('dragstart')
        ->and($source)->not->toContain('dragover')
        ->and($source)->not->toContain('drop=');
});

it('implements select all and indeterminate selection for the visible rows', function (): void {
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

it('filters by search type and status and blocks reorder while a filter is active', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $alpha = $service->createCustomPage('Alpha Search', 'alpha-search');
    $beta = $service->createJournal('Beta Search', 'beta-search', JournalTemplate::Blog->value);
    $service->updatePlacement($beta, 'published', false, null);

    $component = Livewire::test(SitePages::class)
        ->set('search', 'Alpha Search');

    expect(collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all())
        ->toBe([(int) $alpha->getKey()])
        ->and($component->get('filtersActive'))->toBeTrue();

    $before = $alpha->refresh()->position;
    $component->call('sortSection', (int) $alpha->getKey(), 0, 'root');
    expect($alpha->refresh()->position)->toBe($before);

    $component->set('search', '')
        ->set('typeFilter', SiteNodeType::Journal->value)
        ->set('statusFilter', 'published');

    $filtered = collect($component->get('filteredRows'));
    expect($filtered)->not->toBeEmpty()
        ->and($filtered->every(fn (array $row): bool => $row['type'] === SiteNodeType::Journal->value))->toBeTrue()
        ->and($filtered->every(fn (array $row): bool => $row['state'] === 'published'))->toBeTrue();
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
});

it('moves a published in-menu page under a hidden off-menu parent without changing page visibility state', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $parent = $service->createCustomPage('Hidden Parent', 'hidden-parent-placement');
    $page = $service->createCustomPage('Visible Child', 'visible-child-placement');
    $service->updatePlacement($page, 'published', true, null);

    expect($parent->refresh()->state)->toBe('hidden')
        ->and($parent->show_in_navigation)->toBeFalse()
        ->and($page->refresh()->state)->toBe('published')
        ->and($page->show_in_navigation)->toBeTrue();

    expect($order->moveTo($page, (int) $parent->getKey(), 0))->toBeTrue();
    expect($page->refresh()->parent_id)->toBe((int) $parent->getKey())
        ->and($page->state)->toBe('published')
        ->and($page->show_in_navigation)->toBeTrue()
        ->and($parent->refresh()->state)->toBe('hidden')
        ->and($parent->show_in_navigation)->toBeFalse();
});

it('allows Home published and in-menu under a hidden off-menu parent without mutating Home visibility state', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Hidden Home Parent', 'hidden-home-parent-placement');

    $home->setAttribute('navigation_label', 'Home');
    $home->save();
    $service->updatePlacement($home, 'published', true, null);

    expect($parent->refresh()->state)->toBe('hidden')
        ->and($parent->show_in_navigation)->toBeFalse()
        ->and($home->refresh()->state)->toBe('published')
        ->and($home->show_in_navigation)->toBeTrue();

    expect($order->moveTo($home, (int) $parent->getKey(), 0))->toBeTrue();
    expect($home->refresh()->parent_id)->toBe((int) $parent->getKey())
        ->and($home->state)->toBe('published')
        ->and($home->type)->toBe(SiteNodeType::Home->value)
        ->and($home->show_in_navigation)->toBeTrue();
});

it('lets a parent change publication and navigation without mutating its child state or placement', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $parent = $service->createCustomPage('Independent Parent', 'independent-parent-placement');
    $child = $service->createCustomPage('Independent Child', 'independent-child-placement');
    $service->updatePlacement($parent, 'published', true, null);
    $service->updatePlacement($child, 'published', true, (int) $parent->getKey());

    $childParentId = (int) $child->refresh()->parent_id;
    expect($child->state)->toBe('published')
        ->and($child->show_in_navigation)->toBeTrue();

    $service->updatePlacement($parent, 'hidden', false, null);

    expect($parent->refresh()->state)->toBe('hidden')
        ->and($parent->show_in_navigation)->toBeFalse()
        ->and($child->refresh()->parent_id)->toBe($childParentId)
        ->and($child->state)->toBe('published')
        ->and($child->show_in_navigation)->toBeTrue();
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

it('allows Home to be a parent without changing its publication invariants', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $child = $service->createCustomPage('Under Home', 'under-home-repair');

    expect($order->moveTo($child, (int) $home->getKey(), 0))->toBeTrue();
    expect($child->refresh()->parent_id)->toBe((int) $home->getKey())
        ->and($home->refresh()->state)->toBe('published')
        ->and($home->type)->toBe(SiteNodeType::Home->value)
        ->and($home->slug)->toBeNull();
});

it('allows Home to be a child while keeping Home non delete non convert and permanently published', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Home Parent', 'home-parent-repair');

    expect($order->moveTo($home, (int) $parent->getKey(), 0))->toBeTrue();
    expect($home->refresh()->parent_id)->toBe((int) $parent->getKey())
        ->and($home->type)->toBe(SiteNodeType::Home->value)
        ->and($home->state)->toBe('published')
        ->and($home->slug)->toBeNull();

    expect(fn () => $service->updatePlacement($home, 'hidden', false, (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Home is always published.')
        ->and(fn () => $service->convertType($home, SiteNodeType::CustomPage->value))
        ->toThrow(ValidationException::class, 'Home cannot be converted')
        ->and(fn () => $home->refresh()->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');

    $home->refresh();
    $home->setAttribute('type', SiteNodeType::CustomPage->value);
    $home->setAttribute('slug', 'home-conversion-repair');
    expect(fn () => $home->save())
        ->toThrow(ValidationException::class, 'Home cannot be converted');
    expect($home->refresh()->type)->toBe(SiteNodeType::Home->value)
        ->and($home->state)->toBe('published');
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
