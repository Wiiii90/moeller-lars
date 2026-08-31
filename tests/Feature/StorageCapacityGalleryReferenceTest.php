<?php

use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\StorageCapacity;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('distinguishes two concrete galleries for one original without double-counting authoritative bytes', function (): void {
    Storage::fake('local');
    config()->set('media.disk', 'local');
    config()->set('media.quota_bytes', 1_000_000);
    Cache::flush();
    $this->actingAs(User::factory()->admin()->create(), 'web');

    Storage::disk('local')->put('originals/multi-gallery.jpg', str_repeat('x', 240));
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/multi-gallery.jpg',
        'original_filename' => 'multi-gallery.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 240,
        'sha256' => hash('sha256', 'multi-gallery'),
        'state' => 'available',
        'alt_text' => 'Multi gallery',
    ]);

    $galleries = [];
    foreach (['Selected Works', 'Drawings'] as $index => $name) {
        $category = ArtworkCategory::query()->create([
            'slug' => 'storage-multi-gallery-'.$index,
            'name' => $name,
        ]);
        SiteSection::query()->create([
            'type' => SiteNodeType::Gallery->value,
            'template' => null,
            'title' => $name,
            'navigation_label' => $name,
            'slug' => 'storage-multi-gallery-page-'.$index,
            'state' => 'hidden',
            'position' => 950 + $index,
            'show_in_navigation' => false,
            'parent_id' => null,
            'artwork_category_id' => $category->getKey(),
        ]);
        $artwork = Artwork::query()->create([
            'artwork_category_id' => $category->getKey(),
            'slug' => 'storage-multi-artwork-'.$index,
            'title' => $name.' artwork',
            'state' => 'draft',
            'position' => 0,
        ]);
        ArtworkMedia::query()->create([
            'artwork_id' => $artwork->getKey(),
            'media_asset_id' => $asset->getKey(),
            'role' => 'primary',
            'position' => 0,
        ]);
        $galleries[] = $name;
    }

    $component = Livewire::test(StorageCapacity::class);
    $row = collect($component->get('files'))->firstWhere('filename', 'multi-gallery.jpg');
    $galleryTargets = collect($row['references'])
        ->where('area', 'galleries')
        ->pluck('target_label')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($galleryTargets)->toBe(collect($galleries)->sort()->values()->all())
        ->and($row['bucket_key'])->toBe('galleries')
        ->and(collect($component->get('breakdown'))->sum('bytes'))->toBe(240);

    $selected = collect($component->get('referenceOptions'))->firstWhere('label', 'Selected Works');
    expect($selected)->not->toBeNull();

    $component->call('selectReference', $selected['key'])
        ->assertSet('total', 1)
        ->assertSet('referenceFilter', $selected['key']);
});
