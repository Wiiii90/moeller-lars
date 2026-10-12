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

function pagesRepairAdmin(): User
{
    return User::factory()->admin()->create();
}


it('suggests a public slug from a new page name without replacing manual edits', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $component = Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->set('mountedActions.0.data.name', 'Über meine Arbeiten')
        ->assertSet('mountedActions.0.data.slug', 'uber-meine-arbeiten');

    $component
        ->set('mountedActions.0.data.slug', 'my-own-url')
        ->set('mountedActions.0.data.name', 'Anderer Titel')
        ->assertSet('mountedActions.0.data.slug', 'my-own-url')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect(SiteSection::query()->where('slug', 'my-own-url')->value('title'))->toBe('Anderer Titel');
});

it('keeps generated page slugs within the allowed length and syntax', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $component = Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->set('mountedActions.0.data.name', str_repeat('Long Page ', 15));

    $slug = $component->get('mountedActions.0.data.slug');
    expect($slug)->toBeString()
        ->and(strlen($slug))->toBeLessThanOrEqual(80)
        ->and($slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
});

it('derives all six metrics from real SiteSections', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
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
        'galleries' => SiteSection::query()->where('type', SiteSectionType::Gallery->value)->count(),
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

it('keeps Home fixed at the top level and preserves its publication delete and conversion guards', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $home = SiteSection::query()->where('type', SiteSectionType::Home->value)->firstOrFail();
    $parent = $service->createCustomPage('Home Parent', 'home-parent-repair');

    expect(fn () => $order->moveTo($home, (int) $parent->getKey(), 0))
        ->toThrow(ValidationException::class, 'Home is always the first top-level page.');
    expect($home->refresh()->parent_id)->toBeNull()
        ->and($home->state)->toBe('published');

    expect(fn () => $service->updatePlacement($home, 'hidden', (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Home is always published.')
        ->and(fn () => $service->convertType($home, SiteSectionType::CustomPage->value))
        ->toThrow(ValidationException::class, 'Home cannot be converted')
        ->and(fn () => $home->refresh()->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');
});

it('does not report success for no-op page type or Journal template commands', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('No-op Type Page', 'noop-type-page');
    $journal = $service->createJournal('No-op Template Journal', 'noop-template-journal', JournalTemplate::Blog->value);
    $eventsBefore = AuditEvent::query()->count();

    Livewire::test(SitePages::class)
        ->call('convertSectionType', (int) $page->getKey(), SiteSectionType::CustomPage->value)
        ->call('changeJournalTemplate', (int) $journal->getKey(), JournalTemplate::Blog->value)
        ->assertNotDispatched('admin-notification-ticker');

    expect(AuditEvent::query()->count())->toBe($eventsBefore)
        ->and($page->fresh()->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($journal->fresh()->template)->toBe(JournalTemplate::Blog->value);
});

it('initializes target configuration for safe type conversions transactionally', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Convertible Page', 'convertible-page-repair');

    $gallery = $service->convertType($page, SiteSectionType::Gallery->value);
    $galleryId = (int) $gallery->artwork_category_id;
    expect($gallery->type)->toBe(SiteSectionType::Gallery->value)
        ->and($galleryId)->toBeGreaterThan(0)
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeTrue()
        ->and($gallery->customPageSetting()->exists())->toBeFalse();

    $journal = $service->convertType($gallery, SiteSectionType::Journal->value);
    expect($journal->type)->toBe(SiteSectionType::Journal->value)
        ->and($journal->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->journalSetting()->exists())->toBeTrue()
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeFalse();

    $group = $service->convertType($journal, SiteSectionType::NavigationNode->value);
    expect($group->type)->toBe(SiteSectionType::NavigationNode->value)
        ->and($group->slug)->toBeNull()
        ->and($group->journalSetting()->exists())->toBeFalse();

    $custom = $service->convertType($group, SiteSectionType::CustomPage->value);
    expect($custom->type)->toBe(SiteSectionType::CustomPage->value)
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

    expect(fn () => $service->convertType($page, SiteSectionType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains components');
    expect($page->refresh()->type)->toBe(SiteSectionType::CustomPage->value)
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
        ->and(fn () => $service->convertType($journal, SiteSectionType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains entries');

    expect($journal->refresh()->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->type)->toBe(SiteSectionType::Journal->value);
});

it('flattens nested pages in canonical order when nesting is disabled and blocks new nesting', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $homeId = (int) SiteSection::query()->where('type', SiteSectionType::Home->value)->firstOrFail()->getKey();
    $existingRootIds = SiteSection::query()
        ->whereNull('parent_id')
        ->orderBy('position')
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->values()
        ->all();

    expect($existingRootIds[0] ?? null)->toBe($homeId);

    $rootA = $service->createCustomPage('Root A Settings', 'root-a-settings');
    $childA = $service->createNavigationGroup('Child A Settings');
    $childB = $service->createNavigationGroup('Child B Settings');
    $rootB = $service->createCustomPage('Root B Settings', 'root-b-settings');

    $order->moveTo($childA, (int) $rootA->getKey(), 0);
    $order->moveTo($childB, (int) $rootA->getKey(), 1);

    $expectedIds = [
        ...$existingRootIds,
        (int) $rootA->getKey(),
        (int) $childA->getKey(),
        (int) $childB->getKey(),
        (int) $rootB->getKey(),
    ];
    $expectedPositions = array_map(
        static fn (int $index): int => ($index + 1) * 10,
        array_keys($expectedIds),
    );

    app(PagesSettingsDialog::class)->save([
        'navigation_nesting_enabled' => false,
        'skip_home' => false,
        'skip_target_section_id' => null,
    ]);

    $ordered = SiteSection::query()->orderBy('position')->orderBy('id')->get();

    expect(PublicContentSetting::navigationNestingEnabled())->toBeFalse()
        ->and($ordered->pluck('parent_id')->filter()->all())->toBe([])
        ->and($ordered->pluck('id')->map(fn ($id): int => (int) $id)->values()->all())->toBe($expectedIds)
        ->and($ordered->pluck('position')->values()->all())->toBe($expectedPositions);

    expect(fn () => $order->moveTo($rootB->refresh(), (int) $rootA->getKey(), 0))
        ->toThrow(ValidationException::class, 'Nested pages are disabled in Pages settings.');

    $component = Livewire::test(SitePages::class);
    expect($component->get('nestedNavigationEnabled'))->toBeFalse()
        ->and($component->get('metrics')['children'])->toBe(0);
});

it('keeps the Pages Settings action before Add page and exposes the shared Home routing controls', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/site-pages.blade.php'));
    $settingsPosition = strpos($view, "mountAction('pagesSettings')");
    $addPosition = strpos($view, "mountAction('addPage')");

    expect($settingsPosition)->not->toBeFalse()
        ->and($addPosition)->not->toBeFalse()
        ->and($settingsPosition)->toBeLessThan($addPosition);

    expect(app(PagesSettingsDialog::class)->fill())->toHaveKeys([
        'navigation_nesting_enabled',
        'skip_home',
        'skip_target_section_id',
    ]);
});

it('autosaves Pages toggles from field updates without a separate Action submit', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $sections = app(SiteSectionEditorialService::class);
    $destination = $sections->createCustomPage('Autosave Destination', 'autosave-destination');
    $sections->updatePlacement($destination, 'published', null);
    $routing = app(HomeRoutingSettingsService::class);
    $home = app(HomePresentationResolver::class)->settings();

    $component = Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeFalse()
        ->and(PublicContentSetting::navigationNestingEnabled())->toBeTrue();

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    // A real Toggle updates Livewire state; it does not submit the modal Action.
    $component->set('mountedActions.0.data.skip_home', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.navigation_nesting_enabled', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect(PublicContentSetting::navigationNestingEnabled())->toBeFalse()
        ->and($routing->enabled($home->fresh()))->toBeTrue()
        ->and($component->get('nestedNavigationEnabled'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    // A duplicate browser change/submission must be a true no-op.
    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    $component->set('mountedActions.0.data.navigation_nesting_enabled', false);
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);

    $component->call('unmountAction')->assertSet('mountedActions', []);

    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.data.skip_home', true)
        ->assertSet('mountedActions.0.data.navigation_nesting_enabled', false);
});

it('autosaves Skip Home destination selection and avoids duplicate mutations on no-op', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $pages = app(SiteSectionEditorialService::class);
    $first = $pages->createCustomPage('Settings Default Destination', 'settings-default-destination');
    $second = $pages->createCustomPage('Settings Selected Destination', 'settings-selected-destination');
    $pages->updatePlacement($first, 'published', null);
    $pages->updatePlacement($second, 'published', null);

    $home = app(HomePresentationResolver::class)->settings();
    $routing = app(HomeRoutingSettingsService::class);
    $component = Livewire::test(SitePages::class)->call('mountAction', 'pagesSettings')
        ->set('mountedActions.0.data.skip_home', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $component
        ->set('mountedActions.0.data.skip_target_section_id', (string) $second->getKey())
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect($routing->enabled($home->fresh()))->toBeTrue()
        ->and($routing->configuredTargetId($home->fresh()))->toBe((int) $second->getKey());

    $auditCount = AuditEvent::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($auditCount);

    $component->call('unmountAction');
    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->assertSet('mountedActions.0.data.skip_target_section_id', $second->getKey());
});

it('immediately flattens nested pages through Settings autosave without a second submit', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $pages = app(SiteSectionEditorialService::class);
    $parent = $pages->createCustomPage('Autosave Parent', 'autosave-parent');
    $child = $pages->createCustomPage('Autosave Child', 'autosave-child');
    app(SiteSectionOrderService::class)->moveTo($child, (int) $parent->getKey(), 0);

    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->set('mountedActions.0.data.navigation_nesting_enabled', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'pagesSettings');

    expect(PublicContentSetting::navigationNestingEnabled())->toBeFalse()
        ->and($child->fresh()->parent_id)->toBeNull();
});

it('closing an unchanged Pages Settings dialog does not create Activity or Notifications', function (): void {
    $this->actingAs(pagesRepairAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    Livewire::test(SitePages::class)
        ->call('mountAction', 'pagesSettings')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);

    expect(PublicContentSetting::navigationNestingEnabled())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});

it('uses the shared toggle control and the native autosave dialog without Pages helper copy', function (): void {
    $action = file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePagesSettings.php'));
    $pages = file_get_contents(app_path('Filament/Support/PagesSettingsDialog.php'));
    $routing = file_get_contents(app_path('Filament/Support/HomeRoutingDialog.php'));
    $controls = file_get_contents(app_path('Filament/Support/Controls/AdminControl.php'));

    expect($action)->toContain('AdminDialog::edit(')->not->toContain('AdminDialog::command(')
        ->and($action)->not->toContain("'Save settings'")
        ->and($pages)->toContain("AdminControl::toggle('navigation_nesting_enabled'")
        ->and($pages)->toContain('$this->homeRouting->schema($onToggleUpdated)')
        ->and($pages)->toContain('$nesting->afterStateUpdated($onToggleUpdated);')
        ->and($routing)->toContain('$skipHome->afterStateUpdated($onToggleUpdated);')
        ->and($pages)->not->toContain('Placeholder::make(')
        ->and($pages)->not->toContain('Allow one child level below top-level pages')
        ->and($routing)->toContain("AdminControl::toggle('skip_home'")
        ->and($routing)->not->toContain('->helperText(')
        ->and($controls)->toContain('Toggle::configureUsing');
});



it('keeps Home Settings free of explanatory placeholders and field help text', function (): void {
    $home = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));
    $routing = file_get_contents(app_path('Filament/Support/HomeRoutingDialog.php'));

    expect($home)->toContain("Select::make('template')")
        ->and($home)->toContain("Toggle::make('public_site_gate')")
        ->and($home)->not->toContain('Placeholder::make(')
        ->and($home)->not->toContain('->helperText(')
        ->and($routing)->toContain("AdminControl::toggle('skip_home'")
        ->and($routing)->toContain("Select::make('skip_target_section_id')")
        ->and($routing)->not->toContain('->helperText(');
});


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
