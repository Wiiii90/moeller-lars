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
