<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Models\SiteSection;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/PagesFixtures.php';

it('derives the six wide metrics and compact navigation count from real SiteSections', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $parent = $service->createCustomPage('Metric Parent', 'metric-parent');
    $child = $service->createJournal('Metric Child', 'metric-child', JournalTemplate::Blog->value);
    $service->updatePlacement($parent, 'published', null);
    app(SiteSectionOrderService::class)->moveTo($child, (int) $parent->getKey(), 0);

    $metrics = Livewire::test(SitePages::class)->get('metrics');

    expect($metrics)->toBe([
        'total' => SiteSection::query()->count(),
        'published' => SiteSection::query()->where('state', 'published')->count(),
        'unpublished' => SiteSection::query()->where('state', '!=', 'published')->count(),
        'top_level' => SiteSection::query()->whereNull('parent_id')->count(),
        'children' => SiteSection::query()->whereNotNull('parent_id')->count(),
        'navigation' => SiteSection::query()->where('state', 'published')->count(),
        'galleries' => SiteSection::query()->where('type', SiteSectionType::Gallery->value)->count(),
    ]);

    Livewire::test(SitePages::class)->assertSee('In navigation');

    // Skip Home hides Home from the public navigation, not from Published.
    app(HomeRoutingSettingsService::class)->update(
        app(HomePresentationResolver::class)->settings(),
        true,
        null,
    );
    $skipHomeMetrics = Livewire::test(SitePages::class)->get('metrics');
    expect($skipHomeMetrics['published'])->toBe($metrics['published'])
        ->and($skipHomeMetrics['navigation'])->toBe($metrics['navigation'] - 1);
});


it('keeps parent context when a child matches a filter and disables reorder', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
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
    $this->actingAs(pagesTestAdmin(), 'web');
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

    $pageOneIds = collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($pageOneIds);

    $component->call('nextPage');
    expect($component->get('pageNumber'))->toBe(2)
        ->and($component->get('selectedSectionIds'))->toBe([])
        ->and(SiteSection::query()->orderBy('id')->pluck('position', 'id')->all())->toBe($positionsBefore);

    $pageTwoIds = collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($pageTwoIds);

    $component->set('search', 'Paged Root 26');
    $matchingId = (int) SiteSection::query()->where('title', 'Paged Root 26')->value('id');
    expect($component->get('pageNumber'))->toBe(1)
        ->and($component->get('filtersActive'))->toBeTrue()
        ->and(array_map('intval', $component->get('selectedSectionIds')))->toBe([$matchingId])
        ->and(array_column($component->get('filteredRows'), 'id'))->toBe([$matchingId])
        ->and($component->get('reorderEnabled'))->toBeFalse();
});


it('rejects ordering from filtered or incomplete page views without a successful mutation', function (bool $filtered): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $pages = app(SiteSectionEditorialService::class);
    $target = $pages->createCustomPage('Guarded order target', 'guarded-order-target');
    foreach (range(1, $filtered ? 1 : 26) as $index) {
        $pages->createNavigationGroup('Guarded root '.$index);
    }
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray();
    $audits = \App\Models\AuditEvent::query()->count();
    $successes = \App\Models\AdminNotification::query()->where('status', 'success')->count();
    $editor = Livewire::test(SitePages::class);
    if ($filtered) {
        $editor->set('search', 'Guarded order target');
    }
    $editor->assertSet('reorderEnabled', false)
        ->call('sortSection', $target->id, 1, 'root')
        ->assertNotDispatched('refresh-sidebar')
        ->assertNotDispatched('admin-header-notification', fn ($name, $params): bool => ($params['notification']['status'] ?? null) === 'success')
        ->call('moveSection', $target->id, 'up')
        ->assertNotDispatched('refresh-sidebar')
        ->assertNotDispatched('admin-header-notification', fn ($name, $params): bool => ($params['notification']['status'] ?? null) === 'success');
    expect(SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray())->toBe($before)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->where('status', 'success')->count())->toBe($successes);
})->with(['filtered view' => true, 'paginated view' => false]);

it('selects all visible Pages from none or mixed, and clears them from all', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    app(SiteSectionEditorialService::class)->createCustomPage('Alpha Selection', 'alpha-selection');
    app(SiteSectionEditorialService::class)->createCustomPage('Beta Selection', 'beta-selection');

    $component = Livewire::test(SitePages::class);
    $visibleIds = collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    expect(count($visibleIds))->toBeGreaterThan(1)
        ->and($component->get('selectedSectionIds'))->toBe([]);

    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($visibleIds);

    $component->set('selectedSectionIds', array_slice($visibleIds, 0, -1));
    expect(count($component->get('selectedSectionIds')))->toBe(count($visibleIds) - 1);

    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($visibleIds);

    $component->call('toggleSelectAll');
    expect($component->get('selectedSectionIds'))->toBe([]);

    // A preselected subset is included by select-all, not remembered/restored
    // when deselecting that complete visible selection.
    $component->set('selectedSectionIds', [$visibleIds[0]]);
    $component->call('toggleSelectAll');
    expect(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($visibleIds);
    $component->call('toggleSelectAll');
    expect($component->get('selectedSectionIds'))->toBe([]);

    // Repeated clicks must not leave a phantom checked header after zero rows.
    $component->call('toggleSelectAll');
    $component->call('toggleSelectAll');
    expect($component->get('selectedSectionIds'))->toBe([]);

    // Selection always follows the currently visible filtered set.
    $component->call('toggleSelectAll');
    $component->set('search', 'Alpha Selection');
    $filtered = collect($component->get('filteredRows'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    expect($filtered)->toHaveCount(1)
        ->and(array_map('intval', $component->get('selectedSectionIds')))->toEqualCanonicalizing($filtered);

    $component->set('search', 'Beta Selection');
    expect($component->get('selectedSectionIds'))->toBe([]);
    $component->set('search', 'no matching page exists');
    $component->call('toggleSelectAll');
    expect($component->get('selectedSectionIds'))->toBe([]);
});

it('remembers Pages filters across visits without retaining row selections', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');

    Livewire::test(SitePages::class)
        ->set('perPage', 50)
        ->set('statusFilter', 'published')
        ->assertHasNoErrors();

    Livewire::test(SitePages::class)
        ->assertSet('perPage', 50)
        ->assertSet('statusFilter', 'published')
        ->assertSet('selectedSectionIds', []);
});
