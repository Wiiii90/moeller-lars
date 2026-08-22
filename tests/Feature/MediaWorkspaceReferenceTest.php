<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\MediaReferenceCatalog;
use App\Filament\Support\SiteNodePresentation;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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

it('builds broad and specific Used in options from canonical site nodes', function (): void {
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

it('projects real reference locations and filters by broad or specific destinations', function (): void {
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
    BlogPost::query()->create([
        'site_section_id' => $journal->id,
        'slug' => 'studio-notes',
        'title' => 'Studio notes',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
        'cover_media_asset_id' => $journalAsset->id,
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
        'label' => 'The Red Painting',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($gallery),
    ]);

    $catalog->loadAssetReferences($journalAsset);
    expect($catalog->references($journalAsset))->toContainEqual([
        'type' => 'Journal: Artist Blog',
        'label' => 'Studio notes',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($journal),
    ]);

    $catalog->loadAssetReferences($customAsset);
    expect($catalog->references($customAsset))->toContainEqual([
        'type' => 'Custom Page: CV',
        'label' => 'Image component',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]);

    $specificGallery = MediaAsset::query();
    $catalog->applyDestinationFilter($specificGallery, 'node:'.$gallery->id);
    expect($specificGallery->pluck('id')->all())->toBe([$galleryAsset->id]);

    $anyGallery = MediaAsset::query();
    $catalog->applyDestinationFilter($anyGallery, 'kind:'.SiteNodeType::Gallery->value);
    expect($anyGallery->pluck('id')->all())->toBe([$galleryAsset->id]);

    $anyJournal = MediaAsset::query();
    $catalog->applyDestinationFilter($anyJournal, 'kind:'.SiteNodeType::Journal->value);
    expect($anyJournal->pluck('id')->all())->toBe([$journalAsset->id]);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyDestinationFilter($anyCustomPage, 'kind:'.SiteNodeType::CustomPage->value);
    expect($anyCustomPage->pluck('id')->all())->toBe([$customAsset->id]);

    $unassigned = MediaAsset::query();
    $catalog->applyDestinationFilter($unassigned, 'unassigned');
    expect($unassigned->pluck('id')->all())->toBe([$other->id]);
});

it('keeps library metrics limited to available media types supported by the canonical policy', function (): void {
    $referenced = workspaceReferenceAsset('metric-referenced.jpg', bytes: 4);
    workspaceReferenceAsset('metric-alt-missing.png', 'image/png', alt: null, bytes: 8);
    workspaceReferenceAsset('metric-video.mp4', 'video/mp4', alt: null, bytes: 12);
    workspaceReferenceAsset('metric-quarantined.jpg', state: 'quarantined', alt: null, bytes: 100);
    workspaceReferenceAsset('metric-unsupported.mp3', 'audio/mpeg', alt: null, bytes: 200);

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
        'files' => 3,
        'images' => 2,
        'videos' => 1,
        'unreferenced' => 2,
        'alt_missing' => 1,
        'bytes' => 24,
    ]);
});

it('keeps CV record pointers referenced without inventing an unrendered Custom Page usage', function (): void {
    $asset = workspaceReferenceAsset('cv-reference.jpg');
    CvEntry::query()->create([
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
    $references = $catalog->references($asset);

    expect($references)->toContainEqual([
        'type' => 'Custom Page: Biography',
        'label' => 'Image component',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ])->and($references)->toContainEqual([
        'type' => 'CV',
        'label' => 'Portrait',
        'url' => null,
    ])->and($references)->not->toContainEqual([
        'type' => 'Custom Page: Biography',
        'label' => 'Portrait',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]);

    $referenced = MediaAsset::query();
    $catalog->applyReferenceFilter($referenced, true);
    expect($referenced->pluck('id')->all())->toContain($asset->id);

    $unreferenced = MediaAsset::query();
    $catalog->applyReferenceFilter($unreferenced, false);
    expect($unreferenced->pluck('id')->all())->not->toContain($asset->id);

    $specificCustomPage = MediaAsset::query();
    $catalog->applyDestinationFilter($specificCustomPage, 'node:'.$custom->id);
    expect($specificCustomPage->pluck('id')->all())->toContain($asset->id);

    expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))
        ->toThrow(ValidationException::class);
});

it('does not classify a CV entry image as Custom Page media merely because a CV list is placed', function (): void {
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

    expect($catalog->references($asset))->toContainEqual([
        'type' => 'CV',
        'label' => 'Entry portrait',
        'url' => null,
    ]);

    $specificCustomPage = MediaAsset::query();
    $catalog->applyDestinationFilter($specificCustomPage, 'node:'.$custom->id);
    expect($specificCustomPage->pluck('id')->all())->not->toContain($asset->id);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyDestinationFilter($anyCustomPage, 'kind:'.SiteNodeType::CustomPage->value);
    expect($anyCustomPage->pluck('id')->all())->not->toContain($asset->id);
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
            'copyright_notice' => 'All rights reserved',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $asset->refresh();
    expect($asset->getAttribute('alt_text'))->toBe('Updated canonical ALT')
        ->and($asset->getAttribute('credit'))->toBe('Studio credit')
        ->and($asset->getAttribute('copyright_notice'))->toBe('All rights reserved')
        ->and($asset->getAttribute('state'))->toBe('available');
});

it('blocks deletion while a Custom Page image component references the asset', function (): void {
    $asset = workspaceReferenceAsset('custom-page.jpg');
    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'CV');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'media_asset_id' => $asset->id,
            'image_decorative' => false,
        ]],
    ]);

    expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))
        ->toThrow(ValidationException::class);
    expect($asset->fresh()->state)->toBe('available');
});
