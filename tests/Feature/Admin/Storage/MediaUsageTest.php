<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaReferenceQuery;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\MediaReferenceCatalog;
use App\Filament\Support\SiteNodePresentation;
use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('media.disk'));
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/MediaReferenceFixtures.php';

it('builds broad and specific Usage destinations from canonical site nodes', function (): void {
    $category = ArtworkCategory::query()->create(['slug' => 'archive', 'name' => 'Archive']);
    $gallery = workspaceReferenceNode(SiteSectionType::Gallery->value, 'Archive', categoryId: $category->id);
    $journal = workspaceReferenceNode(SiteSectionType::Journal->value, 'Studio Notes', JournalTemplate::Blog->value);
    $custom = workspaceReferenceNode(SiteSectionType::CustomPage->value, 'Biography');
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
            'kind:'.SiteSectionType::Gallery->value,
            'node:'.$gallery->id,
            'kind:'.SiteSectionType::Journal->value,
            'node:'.$journal->id,
            'kind:'.SiteSectionType::CustomPage->value,
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
    $gallery = workspaceReferenceNode(SiteSectionType::Gallery->value, 'Paintings', categoryId: $category->id);
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

    $journal = workspaceReferenceNode(SiteSectionType::Journal->value, 'Artist Blog', JournalTemplate::Blog->value);
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

    $custom = workspaceReferenceNode(SiteSectionType::CustomPage->value, 'CV');
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
    $catalog->applyUsageFilter($anyGallery, 'kind:'.SiteSectionType::Gallery->value);
    expect($anyGallery->pluck('id')->all())->toBe([$galleryAsset->id]);

    $anyJournal = MediaAsset::query();
    $catalog->applyUsageFilter($anyJournal, 'kind:'.SiteSectionType::Journal->value);
    expect($anyJournal->pluck('id')->all())->toBe([$journalAsset->id]);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($anyCustomPage, 'kind:'.SiteSectionType::CustomPage->value);
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
    workspaceReferenceNode(SiteSectionType::Gallery->value, 'Metric gallery', categoryId: $category->id);
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


it('projects canonical Custom Page list media through Usage and removes it on asset deletion', function (): void {
    $asset = workspaceReferenceAsset('list-reference.jpg');
    $custom = workspaceReferenceNode(SiteSectionType::CustomPage->value, 'Biography');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'list',
            'published' => true,
            'title' => 'Biography',
            'media_asset_id' => $asset->id,
            'items' => [[
                'published' => true,
                'date' => '2026',
                'title' => 'Current entry',
                'meta' => null,
                'location' => null,
                'url' => null,
                'body' => null,
            ]],
        ]],
    ]);

    $catalog = app(MediaReferenceCatalog::class);
    $catalog->loadAssetReferences($asset);

    expect($catalog->references($asset))->toBe([[
        'type' => 'Custom Page: Biography',
        'label' => 'List component image',
        'url' => app(SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]]);

    $specificCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($specificCustomPage, 'node:'.$custom->id);
    expect($specificCustomPage->pluck('id')->all())->toContain($asset->id);

    $anyCustomPage = MediaAsset::query();
    $catalog->applyUsageFilter($anyCustomPage, 'kind:'.SiteSectionType::CustomPage->value);
    expect($anyCustomPage->pluck('id')->all())->toContain($asset->id);

    expect(app(MediaReferenceQuery::class)->isReferenced($asset))->toBeTrue();
    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();

    $deletedAsset = $asset->fresh();
    $settings = $custom->customPageSetting()->firstOrFail();
    $component = $settings->components()[0];
    $afterDeleteCatalog = app(MediaReferenceCatalog::class);
    $afterDeleteCatalog->loadAssetReferences($deletedAsset);

    expect($deletedAsset->getAttribute('state'))->toBe('deleted')
        ->and($component['media_asset_id'])->toBeNull()
        ->and(app(MediaReferenceQuery::class)->isReferenced($deletedAsset))->toBeFalse()
        ->and($afterDeleteCatalog->references($deletedAsset))->toBe([]);
});
