<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Storage\SiteStorageDatabaseUsageService;
use App\Filament\Support\StorageWorkspaceOverview;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('storage-behavior-test');
    config([
        'media.disk' => 'storage-behavior-test',
        'media.quota_bytes' => 1_000_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
});

function storageBehaviorAsset(string $filename, int $bytes = 100): MediaAsset
{
    $storageKey = 'originals/'.$filename;
    Storage::disk(config('media.disk'))->put($storageKey, str_repeat('x', $bytes));

    return MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => $bytes,
        'sha256' => hash('sha256', 'storage-behavior-'.$filename),
        'state' => 'available',
        'alt_text' => 'Storage behavior test',
    ]);
}

function storageBehaviorGallery(string $name): ArtworkCategory
{
    static $sequence = 0;
    $sequence++;

    $category = ArtworkCategory::query()->create([
        'slug' => str($name)->slug()->append('-storage-behavior-'.$sequence)->toString(),
        'name' => $name,
    ]);

    SiteSection::query()->create([
        'type' => SiteSectionType::Gallery->value,
        'template' => null,
        'title' => $name,
        'navigation_label' => $name,
        'slug' => str($name)->slug()->append('-storage-behavior-page-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 900 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => $category->getKey(),
    ]);

    return $category;
}

function storageBehaviorAttachArtwork(MediaAsset $asset, ArtworkCategory $category, string $title): void
{
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => str($title)->slug()->append('-'.uniqid())->toString(),
        'title' => $title,
        'state' => 'draft',
        'position' => 0,
    ]);

    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
}

function storageBehaviorJournal(string $title): SiteSection
{
    static $sequence = 0;
    $sequence++;

    return SiteSection::query()->create([
        'type' => SiteSectionType::Journal->value,
        'template' => JournalTemplate::Blog->value,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->append('-storage-behavior-journal-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 950 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
}

it('counts generated derivatives and database storage against the site allowance', function (): void {
    $asset = storageBehaviorAsset('allowance.jpg', 80);
    Storage::disk(config('media.disk'))->put('variants/allowance.webp', str_repeat('v', 300));

    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/allowance.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 300,
        'sha256' => hash('sha256', 'storage-behavior-allowance-variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    $database = app(SiteStorageDatabaseUsageService::class)->snapshot();

    expect($database['measurement_available'])->toBeTrue();

    $databaseBytes = (int) $database['logical_bytes'];
    config()->set('media.quota_bytes', $databaseBytes + 400);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot['authoritative_bytes'])->toBe(80)
        ->and($snapshot['generated_bytes'])->toBe(300)
        ->and($snapshot['database_bytes'])->toBe($databaseBytes)
        ->and($snapshot['site_used_bytes'])->toBe($databaseBytes + 380)
        ->and($snapshot['reclaimable_bytes'])->toBe(300)
        ->and($snapshot['remaining_bytes'])->toBe(20)
        ->and($snapshot['status'])->toBe('near_capacity');
});

it('projects concrete gallery destinations without double-counting authoritative bytes', function (): void {
    $asset = storageBehaviorAsset('multi-gallery.jpg', 240);
    $galleryNames = ['Selected Works', 'Drawings'];

    foreach ($galleryNames as $name) {
        storageBehaviorAttachArtwork($asset, storageBehaviorGallery($name), $name.' artwork');
    }

    $overview = app(StorageWorkspaceOverview::class)->snapshot(measure: true);
    $gallery = collect($overview['breakdown'])->firstWhere('key', 'galleries');
    $galleryTargets = collect($overview['attention']['targets'])
        ->where('area', 'galleries')
        ->pluck('label')
        ->sort()
        ->values()
        ->all();

    expect($gallery)->toBeArray()
        ->and($gallery['bytes'])->toBe(240)
        ->and($gallery['usage_filter'])->toBe('kind:gallery')
        ->and(collect($overview['breakdown'])->sum('bytes'))->toBe(240)
        ->and($galleryTargets)->toBe(collect($galleryNames)->sort()->values()->all())
        ->and($overview['attention']['largest_gallery'])->toBeArray()
        ->and($overview['attention']['largest_gallery']['display_bytes'])->not->toBe('0 B');
});

it('keeps multi-area originals exclusive in the donut while exposing each destination', function (): void {
    $asset = storageBehaviorAsset('shared.jpg', 250);
    storageBehaviorAttachArtwork($asset, storageBehaviorGallery('Shared Gallery'), 'Shared work');

    $journal = storageBehaviorJournal('Shared Journal');
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'storage-behavior-shared-post',
        'title' => 'Shared post',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);
    JournalEntryMedia::query()->create([
        'blog_post_id' => $post->getKey(),
        'exhibition_id' => null,
        'media_asset_id' => $asset->getKey(),
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 0,
    ]);

    $overview = app(StorageWorkspaceOverview::class)->snapshot(measure: true);
    $shared = collect($overview['breakdown'])->firstWhere('key', 'shared');
    $targetAreas = collect($overview['attention']['targets'])->pluck('area')->unique()->values()->all();

    expect($shared)->toBeArray()
        ->and($shared['bytes'])->toBe(250)
        ->and($shared['usage_filter'])->toBeNull()
        ->and(collect($overview['breakdown'])->sum('bytes'))->toBe(250)
        ->and($targetAreas)->toContain('galleries', 'journal');
});

it('resolves concrete gallery references with bounded relation queries', function (): void {
    $gallery = storageBehaviorGallery('Query Gallery');
    foreach (range(1, 12) as $index) {
        $asset = storageBehaviorAsset('query-'.$index.'.jpg', 10 + $index);
        storageBehaviorAttachArtwork($asset, $gallery, 'Query work '.$index);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(StorageWorkspaceOverview::class)->snapshot(measure: true);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $artworkRelationQueries = collect($queries)->filter(static function (array $query): bool {
        $sql = strtolower((string) ($query['query'] ?? ''));

        return str_contains($sql, 'artwork_media') && str_contains($sql, 'select');
    });

    expect($artworkRelationQueries->count())->toBeLessThanOrEqual(3);
});
