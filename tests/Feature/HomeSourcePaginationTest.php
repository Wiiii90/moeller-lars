<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Filament\Pages\HomePresentation;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function homeSourcePaginationSettings(): HomePresentationSetting
{
    $home = SiteSection::query()->create([
        'type' => SiteSection::TYPE_HOME,
        'title' => 'Home',
        'navigation_label' => 'Home',
        'slug' => null,
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $settings = new HomePresentationSetting;
    $settings->setAttribute('site_section_id', $home->getKey());
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

function homeSourcePaginationPage(HomePresentationSetting $settings): HomePresentation
{
    $page = new HomePresentation;
    $page->settingsId = (int) $settings->getKey();

    return $page;
}

it('paginates more than ten Gallery sources with the existing 10 and 25 page sizes', function (): void {
    $settings = homeSourcePaginationSettings();
    foreach (range(1, 12) as $index) {
        homeSourcePaginationGallery($index);
    }

    $page = homeSourcePaginationPage($settings);
    $firstPage = $page->sourceRows();

    expect($firstPage->total())->toBe(12)
        ->and($firstPage->perPage())->toBe(10)
        ->and($firstPage->currentPage())->toBe(1)
        ->and($firstPage->count())->toBe(10)
        ->and($firstPage->firstItem())->toBe(1)
        ->and($firstPage->lastItem())->toBe(10)
        ->and($firstPage->hasMorePages())->toBeTrue();

    $page->selectedSourceIds = [(int) $firstPage->items()[0]['id']];
    $page->goToSourcePage(2);
    $secondPage = $page->sourceRows();

    expect($page->selectedSourceIds)->toBe([])
        ->and($secondPage->currentPage())->toBe(2)
        ->and($secondPage->count())->toBe(2)
        ->and($secondPage->firstItem())->toBe(11)
        ->and($secondPage->lastItem())->toBe(12)
        ->and($secondPage->hasMorePages())->toBeFalse();

    $page->goToSourcePage(1);
    expect($page->sourceRows()->currentPage())->toBe(1);

    $page->selectedSourceIds = [(int) $firstPage->items()[0]['id']];
    $page->sourcePerPage = 25;
    $page->updatedSourcePerPage();
    $allRows = $page->sourceRows();

    expect($page->sourcePage)->toBe(1)
        ->and($page->selectedSourceIds)->toBe([])
        ->and($allRows->perPage())->toBe(25)
        ->and($allRows->count())->toBe(12)
        ->and($allRows->hasMorePages())->toBeFalse();
});

it('resets Home source paging and selection when Search Status or Source filters change', function (): void {
    $settings = homeSourcePaginationSettings();
    foreach (range(1, 12) as $index) {
        homeSourcePaginationGallery($index);
    }

    $page = homeSourcePaginationPage($settings);
    $page->sourcePage = 2;
    $page->selectedSourceIds = [1, 2];
    $page->sourceSearch = 'Gallery 12';
    $page->updatedSourceSearch();

    expect($page->sourcePage)->toBe(1)
        ->and($page->selectedSourceIds)->toBe([])
        ->and($page->sourceRows()->total())->toBe(1);

    $page->resetSourceFilters();
    $page->sourcePage = 2;
    $page->selectedSourceIds = [1];
    $page->sourceStatusFilter = 'published';
    $page->updatedSourceStatusFilter();

    expect($page->sourcePage)->toBe(1)
        ->and($page->selectedSourceIds)->toBe([])
        ->and($page->sourceRows()->total())->toBe(10);

    $page->resetSourceFilters();
    $page->sourcePage = 2;
    $page->selectedSourceIds = [1];
    $page->sourceHomeFilter = 'enabled';
    $page->updatedSourceHomeFilter();

    expect($page->sourcePage)->toBe(1)
        ->and($page->selectedSourceIds)->toBe([])
        ->and($page->sourceRows()->total())->toBe(5);

    $page->sourceHomeFilter = 'disabled';
    $page->updatedSourceHomeFilter();

    expect($page->sourceRows()->total())->toBe(7);
});