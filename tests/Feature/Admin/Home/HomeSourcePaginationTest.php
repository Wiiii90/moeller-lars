<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Filament\Pages\HomePresentation;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function homeSourcePaginationSettings(): HomePresentationSetting
{
    $home = SiteSection::query()
        ->where('type', SiteSection::TYPE_HOME)
        ->firstOrFail();

    $settings = HomePresentationSetting::query()
        ->where('site_section_id', $home->getKey())
        ->firstOrFail();
    $settings->setAttribute('template', 'artwork');
    $settings->setAttribute('configuration', HomePresentationEditorialService::defaults());
    $settings->save();

    return $settings;
}

function homeSourcePaginationGallery(int $index): ArtworkCategory
{
    $gallery = new ArtworkCategory;
    $gallery->fill([
        'slug' => sprintf('home-source-pagination-%02d', $index),
        'name' => sprintf('Gallery %02d', $index),
        'show_on_home' => $index % 2 === 1,
    ]);
    $gallery->save();

    testGallerySection($gallery, [
        'state' => $index <= 10 ? 'published' : 'hidden',
    ]);

    return $gallery;
}

it('paginates more than ten Gallery sources with the existing 10 and 25 page sizes', function (): void {
    homeSourcePaginationSettings();
    foreach (range(1, 12) as $index) {
        homeSourcePaginationGallery($index);
    }

    $component = Livewire::test(HomePresentation::class);
    $firstPage = $component->instance()->sourceRows();

    expect($firstPage->total())->toBe(12)
        ->and($firstPage->perPage())->toBe(10)
        ->and($firstPage->currentPage())->toBe(1)
        ->and($firstPage->count())->toBe(10)
        ->and($firstPage->firstItem())->toBe(1)
        ->and($firstPage->lastItem())->toBe(10)
        ->and($firstPage->hasMorePages())->toBeTrue();

    $component->set('selectedSourceIds', [(int) $firstPage->items()[0]['id']])
        ->call('goToSourcePage', 2)
        ->assertSet('selectedSourceIds', []);
    $secondPage = $component->instance()->sourceRows();

    expect($component->get('selectedSourceIds'))->toBe([])
        ->and($secondPage->currentPage())->toBe(2)
        ->and($secondPage->count())->toBe(2)
        ->and($secondPage->firstItem())->toBe(11)
        ->and($secondPage->lastItem())->toBe(12)
        ->and($secondPage->hasMorePages())->toBeFalse();

    $component->call('goToSourcePage', 1);
    expect($component->instance()->sourceRows()->currentPage())->toBe(1);

    $component->set('selectedSourceIds', [(int) $firstPage->items()[0]['id']])
        ->set('sourcePerPage', 25);
    $allRows = $component->instance()->sourceRows();

    expect($component->get('sourcePage'))->toBe(1)
        ->and($component->get('selectedSourceIds'))->toBe([])
        ->and($allRows->perPage())->toBe(25)
        ->and($allRows->count())->toBe(12)
        ->and($allRows->hasMorePages())->toBeFalse();
});

it('resets Home source paging and selection when Search Status or Source filters change', function (): void {
    homeSourcePaginationSettings();
    foreach (range(1, 12) as $index) {
        homeSourcePaginationGallery($index);
    }

    $component = Livewire::test(HomePresentation::class)
        ->set('sourcePage', 2)
        ->set('selectedSourceIds', [1, 2])
        ->set('sourceSearch', 'Gallery 12');

    expect($component->get('sourcePage'))->toBe(1)
        ->and($component->get('selectedSourceIds'))->toBe([])
        ->and($component->instance()->sourceRows()->total())->toBe(1);

    $component->call('resetSourceFilters')
        ->set('sourcePage', 2)
        ->set('selectedSourceIds', [1])
        ->set('sourceStatusFilter', 'published');

    expect($component->get('sourcePage'))->toBe(1)
        ->and($component->get('selectedSourceIds'))->toBe([])
        ->and($component->instance()->sourceRows()->total())->toBe(10);

    $component->call('resetSourceFilters')
        ->set('sourcePage', 2)
        ->set('selectedSourceIds', [1])
        ->set('sourceHomeFilter', 'enabled');

    expect($component->get('sourcePage'))->toBe(1)
        ->and($component->get('selectedSourceIds'))->toBe([])
        ->and($component->instance()->sourceRows()->total())->toBe(5);

    $component->set('sourceHomeFilter', 'disabled');

    expect($component->instance()->sourceRows()->total())->toBe(7);
});

it('persists selected source changes with every audit and exactly one notification then stays silent on no-op', function (): void {
    homeSourcePaginationSettings();
    $first = homeSourcePaginationGallery(2); $second = homeSourcePaginationGallery(4);
    $before = \App\Models\AuditEvent::query()->count();
    $editor = Livewire::test(HomePresentation::class)->set('selectedSourceIds', [$first->id, $second->id])
        ->call('setSelectedGalleryEligibility', true)->assertHasNoErrors();
    expect($first->fresh()->show_on_home)->toBeTrue()->and($second->fresh()->show_on_home)->toBeTrue()
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->set('selectedSourceIds', [$first->id, $second->id])->call('setSelectedGalleryEligibility', true)
        ->assertNotDispatched('admin-header-notification');
    expect(\App\Models\AuditEvent::query()->count())->toBe($before + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('rolls back all selected Home sources when a later Gallery audit fails', function (): void {
    homeSourcePaginationSettings();
    $first = homeSourcePaginationGallery(2); $second = homeSourcePaginationGallery(4);
    $before = \App\Models\AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class); $calls = 0;
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->twice()->andReturnUsing(function (...$parameters) use (&$calls, $realAudit) {
        if (++$calls === 2) { throw new RuntimeException('Audit unavailable'); }
        return $realAudit->record(...$parameters);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(\App\Domain\Artwork\GalleryEditorialService::class);
    expect(fn () => Livewire::test(HomePresentation::class)->set('selectedSourceIds', [$first->id, $second->id])
        ->call('setSelectedGalleryEligibility', true))->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($first->fresh()->show_on_home)->toBeFalse()->and($second->fresh()->show_on_home)->toBeFalse()
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});
