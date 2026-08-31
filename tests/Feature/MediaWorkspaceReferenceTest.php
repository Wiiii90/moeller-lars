<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaReferenceQuery;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\MediaReferenceCatalog;
use App\Filament\Support\SiteNodePresentation;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function workspaceReferenceAsset(
    string $filename,
    string $mime = 'image/jpeg',
    string $state = 'available',
    ?string $alt = 'Test image',
    int $bytes = 4,
): MediaAsset {
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => $mime,
        'byte_size' => $bytes,
        'sha256' => hash('sha256', $filename),
        'state' => $state,
        'alt_text' => $alt,
    ]);
}

function workspaceReferenceNode(string $type, string $title, ?string $template = null, ?int $categoryId = null): SiteSection
{
    static $sequence = 0;

    $sequence++;

    return SiteSection::query()->create([
        'type' => $type,
        'template' => $template,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->append('-workspace-test-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 900 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => $categoryId,
    ]);
}

it('builds broad and specific Usage destinations from canonical site nodes', function (): void {
    $category = ArtworkCategory::query()->create(['slug' => 'archive', 'name' => 'Archive']);
    $gallery = workspaceReferenceNode(SiteNodeType::Gallery->value, 'Archive', categoryId: $category->id);
    $journal = workspaceReferenceNode(SiteNodeType::Journal->value, 'Studio Notes', JournalTemplate::Blog->value);
    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'Biography');
    $custom->customPageSetting()->create(['blocks' => []]);

    $groups = app(MediaReferenceCatalog::class)->destinationGroups();
    $options = collect($groups)->flatMap(fn (array $group): array => $group['options']);

    expect($options->pluck('label')->all())
        ->toContain(
            'Any Gallery',
            'Archive',
            'Any Journal',
            'Studio Notes',
            'Any Custom Page',
            'Biography',
            'Site identity',
        )
        ->and($options->pluck('value')->all())
        ->toContain(
            'kind:'.SiteNodeType::Gallery->value,
            'node:'.$gallery->id,
            'kind:'.SiteNodeType::Journal->value,
            'node:'.$journal->id,
            'kind:'.SiteNodeType::CustomPage->value,
            'node:'.$custom->id,
            'site-identity',
        );
});

it('uses one canonical Usage path for in-use unreferenced broad and specific destinations', function (): void {
    $galleryAsset = workspaceReferenceAsset('gallery.jpg');
    $journalAsset = workspaceReferenceAsset('journal.jpg');
    $customAsset = workspaceReferenceAsset('custom.jpg');
    $other = workspaceReferenceAsset('other.jpg');

    $category = ArtworkCategory::query()->create(['slug' => 'paintings', 'name' => 'Paintings']);
    $gallery = workspaceReferenceNode(SiteNodeType::Gallery->value, 'Paintings', categoryId: $category->id);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->id,
        'slug' => 'red-painting',
        'title' => 'The Red Painting',
        'state' => 'draft',
        'position' => 0,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $galleryAsset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    $journal = workspaceReferenceNode(SiteNodeType::Journal->value, 'Artist Blog', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->id,
        'slug' => 'studio-notes',
        'title' => 'Studio notes',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);
    JournalEntryMedia::query()->create([
        'blog_post_id' => $post->id,
        'media_asset_id' => $journalAsset->id,
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 0,
    ]);

    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'CV');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'media_asset_id' => $customAsset->id,
            'image_decorative' => false,
        ]],
    ]);

    $catalog = app(MediaReferenceCatalog::class);

    $catalog->loadAssetReferences($galleryAsset);
    expect($catalog->references($galleryAsset))->toContainEqual([
        'type' => 'Gallery: Paintings',
        'label' => 'The Red Painting — Primary image',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($gallery),
    ]);

    $catalog->loadAssetReferences($journalAsset);
    expect($catalog->references($journalAsset))->toContainEqual([
        'type' => 'Journal: Blog',
        'label' => 'Studio notes — Cover image',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($journal),
    ]);

    $catalog->loadAssetReferences($customAsset);
    expect($catalog->references($customAsset))->toContainEqual([
        'type' => 'Custom Page: CV',
        'label' => 'Image component',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]);

    $specificGallery = MediaAsset::query();
    $catalog->applyUsageFilter($specificGallery, 'node:'.$gallery->id);
    expect($specificGallery->pluck('id')->all())->toBe([$galleryAsset->id]);

    $anyGallery = MediaAsset::query();
    $catalog->applyUsageFilter($anyGallery, 'kind:'.SiteNodeType::Gallery->value);
    expect($anyGallery->pluck('id')->all())->toBe([$galleryAsset->id]);

    $anyJournal = MediaAsset::query();
    $catalog->applyUsageFilter($anyJournal, 'kind:'.SiteNodeType::Journal->value);
    expect($anyJournal->pluck('id')->all())->toBe([$journalAsset->id]);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($anyCustomPage, 'kind:'.SiteNodeType::CustomPage->value);
    expect($anyCustomPage->pluck('id')->all())->toBe([$customAsset->id]);

    $inUse = MediaAsset::query();
    $catalog->applyUsageFilter($inUse, 'in-use');
    expect($inUse->pluck('id')->all())
        ->toContain($galleryAsset->id, $journalAsset->id, $customAsset->id)
        ->not->toContain($other->id);

    $unreferenced = MediaAsset::query();
    $catalog->applyUsageFilter($unreferenced, 'unreferenced');
    expect($unreferenced->pluck('id')->all())->toBe([$other->id]);
});

it('counts available images videos and audio in the six library metrics', function (): void {
    $referenced = workspaceReferenceAsset('metric-referenced.jpg', bytes: 4);
    workspaceReferenceAsset('metric-image.png', 'image/png', alt: null, bytes: 8);
    workspaceReferenceAsset('metric-video.mp4', 'video/mp4', alt: null, bytes: 12);
    workspaceReferenceAsset('metric-audio.mp3', 'audio/mpeg', alt: null, bytes: 20);
    workspaceReferenceAsset('metric-quarantined.jpg', state: 'quarantined', alt: null, bytes: 100);

    $category = ArtworkCategory::query()->create(['slug' => 'metric-gallery', 'name' => 'Metric gallery']);
    workspaceReferenceNode(SiteNodeType::Gallery->value, 'Metric gallery', categoryId: $category->id);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->id,
        'slug' => 'metric-artwork',
        'title' => 'Metric artwork',
        'state' => 'draft',
        'position' => 0,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $referenced->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    expect(app(MediaReferenceCatalog::class)->libraryMetrics())->toBe([
        'files' => 4,
        'images' => 2,
        'videos' => 1,
        'audio' => 1,
        'unreferenced' => 3,
        'bytes' => 44,
    ]);
});

it('projects migrated CV media through the canonical Custom Page usage only', function (): void {
    $asset = workspaceReferenceAsset('cv-reference.jpg');
    $legacyEntry = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Portrait',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
        'image_media_asset_id' => $asset->id,
    ]);

    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'Biography');
    $custom->customPageSetting()->create([
        'blocks' => [
            [
                'type' => 'image',
                'media_asset_id' => $asset->id,
                'image_decorative' => false,
            ],
            ['type' => 'cv_list'],
        ],
    ]);

    $catalog = app(MediaReferenceCatalog::class);
    $catalog->loadAssetReferences($asset);

    expect($catalog->references($asset))->toBe([[
        'type' => 'Custom Page: Biography',
        'label' => 'Image component',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]]);

    $referenced = MediaAsset::query();
    $catalog->applyUsageFilter($referenced, 'in-use');
    expect($referenced->pluck('id')->all())->toContain($asset->id);

    $unreferenced = MediaAsset::query();
    $catalog->applyUsageFilter($unreferenced, 'unreferenced');
    expect($unreferenced->pluck('id')->all())->not->toContain($asset->id);

    $specificCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($specificCustomPage, 'node:'.$custom->id);
    expect($specificCustomPage->pluck('id')->all())->toContain($asset->id);

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();

    $deletedAsset = $asset->fresh();
    $settings = $custom->customPageSetting()->firstOrFail();
    $remainingMediaIds = collect($settings->components())
        ->pluck('media_asset_id')
        ->filter(static fn (mixed $id): bool => is_numeric($id))
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();
    $afterDeleteCatalog = app(MediaReferenceCatalog::class);
    $afterDeleteCatalog->loadAssetReferences($deletedAsset);

    expect($deletedAsset->getAttribute('state'))->toBe('deleted')
        ->and($remainingMediaIds)->not->toContain((int) $asset->getKey())
        ->and($legacyEntry->fresh()->getAttribute('image_media_asset_id'))->toBeNull()
        ->and(app(MediaReferenceQuery::class)->isReferenced($deletedAsset))->toBeFalse()
        ->and($afterDeleteCatalog->references($deletedAsset))->toBe([]);
});

it('ignores legacy CV media pointers that the current Custom Page runtime does not render', function (): void {
    $asset = workspaceReferenceAsset('cv-entry-only.jpg');
    CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Entry portrait',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
        'image_media_asset_id' => $asset->id,
    ]);

    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'CV records');
    $custom->customPageSetting()->create(['blocks' => [['type' => 'cv_list']]]);

    $catalog = app(MediaReferenceCatalog::class);
    $catalog->loadAssetReferences($asset);

    expect($catalog->references($asset))->toBe([])
        ->and($catalog->libraryMetrics()['unreferenced'])->toBe(1);

    $referenced = MediaAsset::query();
    $catalog->applyUsageFilter($referenced, 'in-use');
    expect($referenced->pluck('id')->all())->not->toContain($asset->id);

    $unreferenced = MediaAsset::query();
    $catalog->applyUsageFilter($unreferenced, 'unreferenced');
    expect($unreferenced->pluck('id')->all())->toContain($asset->id);

    $specificCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($specificCustomPage, 'node:'.$custom->id);
    expect($specificCustomPage->pluck('id')->all())->not->toContain($asset->id);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($anyCustomPage, 'kind:'.SiteNodeType::CustomPage->value);
    expect($anyCustomPage->pluck('id')->all())->not->toContain($asset->id);

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();
    expect($asset->fresh()->state)->toBe('deleted');
});

it('opens Preview and Edit as workspace actions and saves canonical metadata in place', function (): void {
    $asset = workspaceReferenceAsset('dialog-image.jpg');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('preview', ['asset' => $asset->id])
        ->assertMountedActionModalSee('dialog-image.jpg')
        ->assertMountedActionModalSee('Metadata')
        ->assertMountedActionModalSee('Used in');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('edit', ['asset' => $asset->id])
        ->fillForm([
            'alt_text' => 'Updated canonical ALT',
            'credit' => 'Studio credit',
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
            'copyright_notice' => 'All rights reserved',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $asset->refresh();
    expect($asset->getAttribute('alt_text'))->toBe('Updated canonical ALT')
        ->and($asset->getAttribute('credit'))->toBe('Studio credit')
        ->and($asset->getAttribute('copyright_notice_mode'))->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->getAttribute('copyright_notice'))->toBe('All rights reserved')
        ->and($asset->getAttribute('state'))->toBe('available');
});

it('removes Custom Page image references before deleting the asset', function (): void {
    $asset = workspaceReferenceAsset('custom-page.jpg');
    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'CV');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'media_asset_id' => $asset->id,
            'image_decorative' => false,
        ]],
    ]);

    expect(app(MediaReferenceQuery::class)->isReferenced($asset))->toBeTrue();
    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();

    $deletedAsset = $asset->fresh();
    $settings = $custom->customPageSetting()->firstOrFail();
    $afterDeleteCatalog = app(MediaReferenceCatalog::class);
    $afterDeleteCatalog->loadAssetReferences($deletedAsset);

    expect($deletedAsset->getAttribute('state'))->toBe('deleted')
        ->and($settings->components())->toBe([])
        ->and(app(MediaReferenceQuery::class)->isReferenced($deletedAsset))->toBeFalse()
        ->and($afterDeleteCatalog->references($deletedAsset))->toBe([]);
});