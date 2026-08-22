<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function workspaceReferenceAsset(string $filename): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', $filename),
        'state' => 'available',
        'alt_text' => 'Test image',
    ]);
}

function workspaceReferenceNode(string $type, string $title, ?string $template = null, ?int $categoryId = null): SiteSection
{
    return SiteSection::query()->create([
        'type' => $type,
        'template' => $template,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->toString(),
        'state' => 'hidden',
        'position' => random_int(300, 900),
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => $categoryId,
    ]);
}

it('builds Used in options from the canonical site nodes', function (): void {
    $category = ArtworkCategory::query()->create(['slug' => 'archive', 'name' => 'Archive']);
    $gallery = workspaceReferenceNode(SiteNodeType::Gallery->value, 'Archive', categoryId: $category->id);
    $journal = workspaceReferenceNode(SiteNodeType::Journal->value, 'Studio Notes', JournalTemplate::Blog->value);
    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'Biography');
    $custom->customPageSetting()->create(['blocks' => []]);

    $groups = app(MediaReferenceCatalog::class)->destinationGroups();
    $options = collect($groups)->flatMap(fn (array $group): array => $group['options']);

    expect($options->pluck('label')->all())
        ->toContain('Archive', 'Studio Notes', 'Biography', 'Site identity')
        ->and($options->pluck('value')->all())
        ->toContain('node:'.$gallery->id, 'node:'.$journal->id, 'node:'.$custom->id, 'site-identity');
});

it('projects real Gallery and Custom Page reference locations and filters by them', function (): void {
    $asset = workspaceReferenceAsset('shared.jpg');
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
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    $custom = workspaceReferenceNode(SiteNodeType::CustomPage->value, 'CV');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'media_asset_id' => $asset->id,
            'image_decorative' => false,
        ]],
    ]);

    $catalog = app(MediaReferenceCatalog::class);
    $catalog->loadAssetReferences($asset);
    $references = $catalog->references($asset);

    expect($references)->toContainEqual([
        'type' => 'Gallery: Paintings',
        'label' => 'The Red Painting',
        'url' => app(\App\Filament\Support\SiteNodePresentation::class)->workspaceUrl($gallery),
    ])->and($references)->toContainEqual([
        'type' => 'Custom Page: CV',
        'label' => 'Image component',
        'url' => app(\App\Filament\Support\SiteNodePresentation::class)->workspaceUrl($custom->fresh('customPageSetting')),
    ]);

    $galleryQuery = MediaAsset::query();
    $catalog->applyDestinationFilter($galleryQuery, 'node:'.$gallery->id);
    expect($galleryQuery->pluck('id')->all())->toBe([$asset->id]);

    $customQuery = MediaAsset::query();
    $catalog->applyDestinationFilter($customQuery, 'node:'.$custom->id);
    expect($customQuery->pluck('id')->all())->toBe([$asset->id]);

    $unassignedQuery = MediaAsset::query();
    $catalog->applyDestinationFilter($unassignedQuery, 'unassigned');
    expect($unassignedQuery->pluck('id')->all())->toBe([$other->id]);
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
