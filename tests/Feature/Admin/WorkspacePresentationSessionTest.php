<?php

use App\Filament\Pages\SitePages;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Attributes\Session;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('restores Pages view preferences across component visits without retaining row selections', function (): void {
    Livewire::test(SitePages::class)
        ->set('perPage', 50)
        ->set('statusFilter', 'published')
        ->assertHasNoErrors();

    Livewire::test(SitePages::class)
        ->assertSet('perPage', 50)
        ->assertSet('statusFilter', 'published')
        ->assertSet('selectedSectionIds', []);
});

it('keeps only the explicitly opted-in workspace properties in the session contract', function (): void {
    $remembered = [
        \App\Filament\Resources\MediaAssets\Pages\ListMediaAssets::class => ['pageSize', 'size', 'state'],
        \App\Filament\Pages\Analytics::class => ['range', 'detailReport', 'detailPageSize'],
        \App\Filament\Pages\Dashboard::class => ['feedType', 'feedPageSize'],
        SitePages::class => ['typeFilter', 'statusFilter', 'perPage'],
        \App\Filament\Pages\GalleryWorkspace::class => ['statusFilter', 'readinessFilter'],
        \App\Filament\Pages\JournalWorkspace::class => ['pageSize'],
        \App\Filament\Pages\HomePresentation::class => ['sourceStatusFilter', 'sourceHomeFilter', 'sourcePerPage', 'componentType'],
        \App\Filament\Pages\CustomPageWorkspace::class => ['componentType', 'pageSize'],
    ];

    foreach ($remembered as $page => $properties) {
        foreach ($properties as $property) {
            expect((new ReflectionProperty($page, $property))->getAttributes(Session::class))->toHaveCount(1);
        }

        foreach (['selectedSectionIds', 'selectedArtworkIds', 'selectedAssets', 'search', 'feedSearch'] as $temporary) {
            if (! property_exists($page, $temporary)) {
                continue;
            }

            expect((new ReflectionProperty($page, $temporary))->getAttributes(Session::class))->toBe([]);
        }
    }
});
