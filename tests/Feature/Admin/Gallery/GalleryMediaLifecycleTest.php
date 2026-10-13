<?php

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\PublicMedia;
use App\Filament\Support\ArtworkEditDialog;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Support\AdminActivityFeed;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMaterialPreset;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/GalleryFixtures.php';

it('detaches a draft from its Gallery without deleting artwork media or shared MediaAssets', function (): void {
    $gallery = polishGallery();
    $asset = polishAsset();
    $detached = polishArtwork($gallery, ['position' => 0]);
    $remaining = polishArtwork($gallery, ['position' => 1]);
    polishPrimary($detached, $asset);
    polishPrimary($remaining, $asset);

    app(ArtworkGalleryAssignmentService::class)->detach($detached);

    expect($detached->fresh()->artwork_category_id)->toBeNull()
        ->and(Artwork::query()->whereKey($detached->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $detached->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and((int) $remaining->fresh()->position)->toBe(0);
});


it('deletes only the draft and its usages while preserving a shared MediaAsset', function (): void {
    $gallery = polishGallery();
    $asset = polishAsset();
    $deleted = polishArtwork($gallery, ['position' => 0]);
    $other = polishArtwork($gallery, ['position' => 1]);
    polishPrimary($deleted, $asset);
    polishPrimary($other, $asset);

    app(ArtworkDraftService::class)->delete($deleted);

    expect(Artwork::query()->whereKey($deleted->getKey())->exists())->toBeFalse()
        ->and(ArtworkMedia::query()->where('artwork_id', $deleted->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $other->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue();
});


it('preserves replaced MediaAssets in the library even when they become unreferenced', function (): void {
    $artwork = polishArtwork(polishGallery());
    $oldAsset = polishAsset();
    $newAsset = polishAsset();
    polishPrimary($artwork, $oldAsset);

    app(ArtworkPrimaryMediaService::class)->replaceAsset($artwork, $newAsset);

    expect(MediaAsset::query()->whereKey($oldAsset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($newAsset->getKey())->exists())->toBeTrue()
        ->and((int) ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->value('media_asset_id'))
        ->toBe((int) $newAsset->getKey());
});


it('accepts existing image and video MediaAssets as primary and rejects audio', function (): void {
    $service = app(ArtworkPrimaryMediaService::class);

    $imageArtwork = polishArtwork(polishGallery());
    $image = polishAsset('image/jpeg');
    $service->attachAsset($imageArtwork, $image);

    $videoArtwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    $service->attachAsset($videoArtwork, $video);

    $audioArtwork = polishArtwork(polishGallery());
    $audio = polishAsset('audio/mpeg');

    expect((int) $imageArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $image->getKey())
        ->and((int) $videoArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $video->getKey())
        ->and(fn () => $service->attachAsset($audioArtwork, $audio))->toThrow(ValidationException::class);
});


it('detaches Gallery artworks after their shared primary Media File is deleted from the Gallery workspace', function (): void {
    $source = polishGallery();
    $otherGallery = polishGallery();
    $asset = polishAsset();
    $deletedFromSource = polishArtwork($source, ['position' => 0]);
    $remainingInSource = polishArtwork($source, ['position' => 1]);
    $alsoAffected = polishArtwork($otherGallery, ['position' => 0]);
    polishPrimary($deletedFromSource, $asset);
    polishPrimary($alsoAffected, $asset);
    polishUnavailableGalleryAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $source->getKey()])
        ->mountAction('deletePrimaryMedia', ['artwork' => $deletedFromSource->getKey()])
        ->callMountedAction();

    expect(Artwork::query()->whereKey($deletedFromSource->getKey())->exists())->toBeTrue()
        ->and($deletedFromSource->fresh()->getAttribute('artwork_category_id'))->toBeNull()
        ->and($alsoAffected->fresh()->getAttribute('artwork_category_id'))->toBeNull()
        ->and((int) $remainingInSource->fresh()->position)->toBe(0)
        ->and(ArtworkMedia::query()->where('media_asset_id', $asset->getKey())->exists())->toBeFalse()
        ->and($asset->fresh()->getAttribute('state'))->toBe('deleted');
});


it('cleans Gallery membership when a shared primary file is deleted directly from Storage', function (): void {
    $firstGallery = polishGallery();
    $secondGallery = polishGallery();
    $shared = polishAsset();
    $other = polishAsset();
    $first = polishArtwork($firstGallery, ['position' => 0]);
    $kept = polishArtwork($firstGallery, ['position' => 1]);
    $second = polishArtwork($secondGallery, ['position' => 0]);
    polishPrimary($first, $shared);
    polishPrimary($second, $shared);
    polishPrimary($kept, $other);
    polishThumbnail($shared);
    polishThumbnail($other);

    app(ArtworkPublicationService::class)->publish($first);
    app(ArtworkPublicationService::class)->publish($second);
    app(ArtworkPublicationService::class)->publish($kept);

    // This is the Storage domain entry point, not the Gallery delete Action.
    app(MediaAssetEditorialService::class)->delete($shared);

    expect($shared->fresh()->state)->toBe('deleted')
        ->and($first->fresh()->state)->toBe('draft')
        ->and($second->fresh()->state)->toBe('draft')
        ->and($first->fresh()->artwork_category_id)->toBeNull()
        ->and($second->fresh()->artwork_category_id)->toBeNull()
        ->and(ArtworkMedia::query()->where('media_asset_id', $shared->getKey())->exists())->toBeFalse()
        ->and($kept->fresh()->artwork_category_id)->toBe($firstGallery->getKey())
        ->and((int) $kept->fresh()->position)->toBe(0)
        ->and($other->fresh()->state)->toBe('available');

    $this->get(route('preview.site.section', ['section' => $firstGallery->slug]))
        ->assertOk()
        ->assertSee($kept->title)
        ->assertDontSee($first->title);
});
