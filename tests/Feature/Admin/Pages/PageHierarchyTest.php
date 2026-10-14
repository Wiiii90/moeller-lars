<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Filament\Support\PagesSettingsDialog;
use App\Models\AuditEvent;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/PagesFixtures.php';

it('keeps root reorder and child reorder canonical', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
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
    $this->actingAs(pagesTestAdmin(), 'web');
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
    $this->actingAs(pagesTestAdmin(), 'web');
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
    $this->actingAs(pagesTestAdmin(), 'web');
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


it('flattens nested pages in canonical order when nesting is disabled and blocks new nesting', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
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


it('immediately flattens nested pages through Settings autosave without a second submit', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
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

it('persists cross-parent sorting with one audit and notification and ignores the repeated position', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $pages = app(SiteSectionEditorialService::class);
    $parent = $pages->createNavigationGroup('Sort destination');
    $child = $pages->createCustomPage('Sort child', 'sort-child');
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(SitePages::class)->call('sortSection', $child->id, 0, $parent->id)
        ->assertHasNoErrors()->assertDispatched('refresh-sidebar');
    expect($child->fresh()->parent_id)->toBe($parent->id)->and($child->fresh()->position)->toBe(10)
        ->and(AuditEvent::query()->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->call('sortSection', $child->id, 0, $parent->id)
        ->assertNotDispatched('admin-header-notification')->assertNotDispatched('refresh-sidebar');
    expect(AuditEvent::query()->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('rolls back both hierarchy groups when cross-parent sorting cannot record its audit', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $pages = app(SiteSectionEditorialService::class);
    $parent = $pages->createNavigationGroup('Atomic destination');
    $child = $pages->createCustomPage('Atomic child', 'atomic-child');
    $before = SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray();
    $auditCount = AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('recordOrdering')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(SiteSectionOrderService::class);
    expect(fn () => Livewire::test(SitePages::class)->call('sortSection', $child->id, 0, $parent->id))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});

it('rejects direct hierarchy sorting after admin authorization is lost', function (bool $guest): void {
    $actor = pagesTestAdmin();
    $this->actingAs($actor, 'web');
    $pages = app(SiteSectionEditorialService::class);
    $parent = $pages->createNavigationGroup('Protected destination');
    $child = $pages->createCustomPage('Protected child', 'protected-child');
    $editor = Livewire::test(SitePages::class);
    $before = SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray();
    $auditCount = AuditEvent::query()->count();
    if ($guest) { \Illuminate\Support\Facades\Auth::guard('web')->logout(); }
    else { $actor->forceFill(['is_admin' => false])->save(); }
    $editor->call('sortSection', $child->id, 0, $parent->id)->assertForbidden();
    expect(SiteSection::query()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['guest' => true, 'revoked admin' => false]);
