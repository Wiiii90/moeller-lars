<?php

use App\Domain\Media\MediaStorageBreakdown;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function storageBreakdownAsset(string $name, int $bytes): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$name.'.jpg',
        'original_filename' => $name.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => $bytes,
        'sha256' => hash('sha256', $name),
        'state' => 'available',
        'alt_text' => $name,
        'width' => 10,
        'height' => 10,
    ]);
}

it('attributes measured original bytes to exclusive library-use classes without double counting', function (): void {
    $category = ArtworkCategory::query()->create([
        'slug' => 'storage-breakdown',
        'name' => 'Storage breakdown',
        'state' => 'published',
        'position' => 0,
    ]);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'storage-breakdown-work',
        'title' => 'Storage breakdown work',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $artworkOnly = storageBreakdownAsset('artwork-only', 100);
    $blogOnly = storageBreakdownAsset('blog-only', 80);
    $shared = storageBreakdownAsset('shared', 70);
    $unassigned = storageBreakdownAsset('unassigned', 50);

    $artwork->mediaAssets()->attach($artworkOnly->getKey(), [
        'role' => 'primary',
        'position' => 0,
        'alt_text_override' => null,
    ]);
    $artwork->mediaAssets()->attach($shared->getKey(), [
        'role' => 'additional',
        'position' => 1,
        'alt_text_override' => null,
    ]);

    BlogPost::query()->create([
        'title' => 'Blog only',
        'slug' => 'blog-only',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
        'cover_media_asset_id' => $blogOnly->getKey(),
    ]);
    BlogPost::query()->create([
        'title' => 'Shared',
        'slug' => 'shared',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 1,
        'cover_media_asset_id' => $shared->getKey(),
    ]);

    $analysis = app(MediaStorageBreakdown::class)->analyze([
        (string) $artworkOnly->storage_key => 100,
        (string) $blogOnly->storage_key => 80,
        (string) $shared->storage_key => 70,
        (string) $unassigned->storage_key => 50,
        'originals/not-in-database.jpg' => 40,
    ]);
    $rows = $analysis['breakdown'];
    $byKey = collect($rows)->keyBy('key');

    expect($byKey->get('artworks'))->toMatchArray(['bytes' => 100, 'files' => 1])
        ->and($byKey->get('blog'))->toMatchArray(['bytes' => 80, 'files' => 1])
        ->and($byKey->get('shared'))->toMatchArray(['bytes' => 70, 'files' => 1])
        ->and($byKey->get('unassigned'))->toMatchArray(['bytes' => 50, 'files' => 1])
        ->and($byKey->get('uncatalogued'))->toMatchArray(['bytes' => 40, 'files' => 1])
        ->and(array_sum(array_column($rows, 'bytes')))->toBe(340)
        ->and(array_sum(array_column($rows, 'files')))->toBe(5)
        ->and(round((float) array_sum(array_column($rows, 'percent')), 1))->toBe(100.0);

    expect($analysis['heavy_consumers'][0])->toBe([
        'label' => 'artwork-only.jpg',
        'classification' => 'Artworks',
        'bytes' => 100,
    ])->and($analysis['heavy_consumers'][4])->toBe([
        'label' => 'Uncatalogued original',
        'classification' => 'Uncatalogued originals',
        'bytes' => 40,
    ])->and(collect($analysis['heavy_consumers'])->every(
        fn (array $row): bool => ! array_key_exists('storage_key', $row),
    ))->toBeTrue();
});
