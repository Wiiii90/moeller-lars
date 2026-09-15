<?php

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\MediaReferenceCatalog;
use App\Filament\Support\SiteNodePresentation;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function cvCompatibilityAsset(string $filename): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 64,
        'sha256' => hash('sha256', 'cv-compatibility-'.$filename),
        'state' => 'available',
        'alt_text' => 'CV compatibility image',
        'width' => 2,
        'height' => 2,
    ]);
}

it('keeps CV List components as references to canonical CV records with an optional image', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('CV contract', 'cv-compatibility-page');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = cvCompatibilityAsset('cv-list.jpg');
    $entry = CvEntry::query()->create([
        'section' => 'Exhibitions',
        'title' => 'Original CV title',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
    ]);

    $settings->update(['blocks' => [['type' => 'cv_list', 'media_asset_id' => $asset->getKey()]]]);
    $stored = $settings->fresh()->components()[0];

    expect($stored)->toBe([
        'type' => 'cv_list',
        'published' => true,
        'media_asset_id' => (int) $asset->getKey(),
    ])->and($stored)->not->toHaveKey('items');

    $entry->update(['title' => 'Updated canonical CV title']);
    expect(CvEntry::query()->findOrFail($entry->getKey())->title)->toBe('Updated canonical CV title');
});

it('preserves historical CV body and entry image when structured fields are edited', function (): void {
    $asset = cvCompatibilityAsset('cv-history.jpg');
    $entry = CvEntry::query()->create([
        'section' => 'CV',
        'title' => 'Historical CV entry',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2025',
        'body' => 'Historical details',
        'image_media_asset_id' => $asset->getKey(),
    ]);

    app(CvEntryEditorialService::class)->update($entry, [
        'section' => 'CV',
        'title' => 'Structured CV entry',
        'year_text' => '2026',
        'date_precision' => 'year',
        'starts_on' => null,
        'ends_on' => null,
        'organisation' => null,
        'location' => null,
        'external_url' => null,
    ]);

    $fresh = $entry->fresh();
    expect($fresh->getAttribute('title'))->toBe('Structured CV entry')
        ->and($fresh->getAttribute('body'))->toBe('Historical details')
        ->and((int) $fresh->getAttribute('image_media_asset_id'))->toBe((int) $asset->getKey());
});

it('removes CV records without deleting their canonical media assets', function (): void {
    $asset = cvCompatibilityAsset('cv-delete.jpg');
    $entry = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Removable CV entry',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
        'image_media_asset_id' => $asset->getKey(),
    ]);

    app(EditorialRecordService::class)->deleteCv($entry);

    expect(CvEntry::query()->whereKey($entry->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});

it('uses rendered cv_list media as the canonical CV reference and ignores legacy CvEntry media fields', function (): void {
    $portrait = cvCompatibilityAsset('cv-list-portrait.jpg');
    $legacyImage = cvCompatibilityAsset('legacy-cv-entry-image.jpg');
    $legacyBody = cvCompatibilityAsset('legacy-cv-entry-body.jpg');

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
        'type' => SiteSectionType::CustomPage->value,
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
    $section->customPageSetting()->create(['blocks' => [[
        'type' => 'cv_list',
        'published' => true,
        'media_asset_id' => $portrait->getKey(),
    ]]]);

    $catalog = app(MediaReferenceCatalog::class);
    foreach ([$portrait, $legacyImage, $legacyBody] as $asset) {
        $catalog->loadAssetReferences($asset);
    }

    expect($catalog->references($portrait))->toContainEqual([
        'type' => 'CV',
        'label' => 'Biography — Portrait',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($section->fresh('customPageSetting')),
    ])
        ->and($catalog->references($legacyImage))->toBe([])
        ->and($catalog->references($legacyBody))->toBe([]);

    $cv = MediaAsset::query();
    $catalog->applyUsageFilter($cv, 'cv');
    expect($cv->pluck('id')->all())->toBe([$portrait->getKey()]);
});
