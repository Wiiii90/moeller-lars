<?php

use App\Domain\Content\SiteNodeType;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;

function cvReferenceAsset(string $filename): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 64,
        'sha256' => hash('sha256', 'cv-reference-'.$filename),
        'state' => 'available',
        'alt_text' => 'CV reference image',
    ]);
}

it('uses rendered cv_list media as the canonical CV reference and ignores legacy CvEntry media fields', function (): void {
    $portrait = cvReferenceAsset('cv-list-portrait.jpg');
    $legacyImage = cvReferenceAsset('legacy-cv-entry-image.jpg');
    $legacyBody = cvReferenceAsset('legacy-cv-entry-body.jpg');

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
        'slug' => 'cv-list-reference-test',
        'state' => 'hidden',
        'position' => 980,
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

    $catalog = app(MediaReferenceCatalog::class);
    foreach ([$portrait, $legacyImage, $legacyBody] as $asset) {
        $catalog->loadAssetReferences($asset);
    }

    expect($catalog->references($portrait))->toContainEqual([
        'type' => 'CV',
        'label' => 'Biography — Portrait',
        'url' => app(\App\Filament\Support\SiteNodePresentation::class)->workspaceUrl($section->fresh('customPageSetting')),
    ])
        ->and($catalog->references($legacyImage))->toBe([])
        ->and($catalog->references($legacyBody))->toBe([]);

    $inUse = MediaAsset::query();
    $catalog->applyUsageFilter($inUse, 'in-use');
    expect($inUse->pluck('id')->all())
        ->toContain($portrait->getKey())
        ->not->toContain($legacyImage->getKey(), $legacyBody->getKey());

    $cv = MediaAsset::query();
    $catalog->applyUsageFilter($cv, 'cv');
    expect($cv->pluck('id')->all())->toBe([$portrait->getKey()]);

    $specificPage = MediaAsset::query();
    $catalog->applyUsageFilter($specificPage, 'node:'.$section->getKey());
    expect($specificPage->pluck('id')->all())->toBe([$portrait->getKey()]);

    $unreferenced = MediaAsset::query();
    $catalog->applyUsageFilter($unreferenced, 'unreferenced');
    expect($unreferenced->pluck('id')->all())
        ->toContain($legacyImage->getKey(), $legacyBody->getKey())
        ->not->toContain($portrait->getKey());
});
