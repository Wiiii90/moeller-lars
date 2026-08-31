<?php

use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\StorageCapacity;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function storageCvAsset(string $filename): MediaAsset
{
    $storageKey = 'originals/'.$filename;
    Storage::disk('local')->put($storageKey, str_repeat('x', 64));

    return MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 64,
        'sha256' => hash('sha256', 'storage-cv-'.$filename),
        'state' => 'available',
        'alt_text' => 'Storage CV image',
    ]);
}

it('uses cv_list portrait media in Storage while legacy CvEntry media stays unused', function (): void {
    Storage::fake('local');
    config()->set('media.disk', 'local');
    config()->set('media.quota_bytes', 1_000_000);
    Cache::flush();
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $portrait = storageCvAsset('rendered-cv-list.jpg');
    $legacyImage = storageCvAsset('legacy-cv-image.jpg');
    $legacyBody = storageCvAsset('legacy-cv-body.jpg');

    CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Legacy entry',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
        'body' => '![](media:'.$legacyBody->getKey().')',
        'image_media_asset_id' => $legacyImage->getKey(),
    ]);

    $section = SiteSection::query()->create([
        'type' => SiteNodeType::CustomPage->value,
        'template' => null,
        'title' => 'Biography',
        'navigation_label' => 'Biography',
        'slug' => 'storage-cv-reference',
        'state' => 'hidden',
        'position' => 975,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $section->customPageSetting()->create([
        'blocks' => [[
            'type' => 'cv_list',
            'published' => true,
            'media_asset_id' => $portrait->getKey(),
        ]],
    ]);

    $rows = collect(Livewire::test(StorageCapacity::class)->get('files'))->keyBy('filename');

    expect($rows['rendered-cv-list.jpg']['state'])->toBe('referenced')
        ->and($rows['rendered-cv-list.jpg']['use_labels'])->toContain('CV')
        ->and(collect($rows['rendered-cv-list.jpg']['references'])->pluck('target_label')->all())->toContain('Biography')
        ->and($rows['legacy-cv-image.jpg']['state'])->toBe('unreferenced')
        ->and($rows['legacy-cv-body.jpg']['state'])->toBe('unreferenced');
});
