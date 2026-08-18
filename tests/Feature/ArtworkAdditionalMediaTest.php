<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $this->category = ArtworkCategory::query()->create([
        'slug' => 'additional-media-test',
        'name' => 'Additional media test',
        'state' => 'published',
        'position' => 0,
    ]);

    $this->artwork = Artwork::query()->create([
        'artwork_category_id' => $this->category->getKey(),
        'slug' => 'additional-media-artwork',
        'title' => 'Additional media artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
});

function additionalMediaAsset(string $name): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$name.'.jpg',
        'original_filename' => $name.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 3,
        'sha256' => hash('sha256', $name),
        'state' => 'available',
        'alt_text' => $name,
        'width' => 10,
        'height' => 10,
    ]);
}

it('attaches available library media as ordered additional artwork media', function () {
    $firstAsset = additionalMediaAsset('first');
    $secondAsset = additionalMediaAsset('second');

    $first = app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, $firstAsset);
    $second = app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, $secondAsset);

    expect($first->role)->toBe('additional')
        ->and($first->position)->toBe(1)
        ->and($second->position)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork.additional_media_attached')->count())->toBe(2);
});

it('does not attach the same library asset twice to one artwork', function () {
    $asset = additionalMediaAsset('duplicate');
    app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, $asset);

    expect(fn () => app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, $asset))
        ->toThrow(ValidationException::class)
        ->and(ArtworkMedia::query()->where('artwork_id', $this->artwork->getKey())->count())->toBe(1);
});

it('moves additional images without exposing numeric order editing', function () {
    $first = app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, additionalMediaAsset('move-first'));
    $second = app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, additionalMediaAsset('move-second'));

    app(ArtworkEditorialService::class)->moveAdditionalMedia($this->artwork, $second, 'up');

    expect($second->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork.additional_media_reordered')->count())->toBe(1);
});

it('detaches an additional image but keeps the media asset in the library', function () {
    $asset = additionalMediaAsset('detached');
    $usage = app(ArtworkEditorialService::class)->attachAdditionalMedia($this->artwork, $asset);

    app(ArtworkEditorialService::class)->detachAdditionalMedia($this->artwork, $usage);

    expect(ArtworkMedia::query()->whereKey($usage->getKey())->exists())->toBeFalse()
        ->and($asset->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'artwork.additional_media_detached')->count())->toBe(1);
});
