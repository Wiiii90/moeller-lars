<?php

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\Artwork;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('media.disk'));
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('deletes an empty draft Gallery slot without requiring primary media', function (): void {
    $gallery = galleryFixtureCategory();
    $draft = galleryFixtureArtwork($gallery, ['position' => 0]);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('deleteArtwork', ['artwork' => $draft->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect(Artwork::query()->whereKey($draft->getKey())->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'artwork.deleted')->where('entity_id', $draft->getKey())->count())->toBe(1)
        ->and(AdminNotification::query()->where('title', 'Artwork deleted')->count())->toBe(1);
});

it('rejects direct deletion of a published Gallery artwork without destroying its media', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $asset = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($artwork, $asset);
    app(ArtworkPublicationService::class)->publish($artwork);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('deleteArtwork', ['artwork' => $artwork->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Artwork::query()->whereKey($artwork->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and(AdminNotification::query()->latest('id')->value('status'))->toBe('danger');
});

it('deletes an Artwork slot but preserves its shared Storage file unless opted in', function (): void {
    $gallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $one = galleryFixtureArtwork($gallery, ['position' => 0]);
    $two = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($one, $asset);
    galleryFixturePrimary($two, $asset);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('deleteArtwork', ['artwork' => $one->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Artwork::query()->whereKey($one->getKey())->exists())->toBeFalse()
        ->and($asset->fresh()->getAttribute('state'))->toBe('available')
        ->and($two->fresh()->getAttribute('artwork_category_id'))->toBe($gallery->getKey())
        ->and((int) $two->fresh()->getAttribute('position'))->toBe(0);
});

it('detaches a draft from its Gallery without deleting artwork media or shared MediaAssets', function (): void {
    $gallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $detached = galleryFixtureArtwork($gallery, ['position' => 0]);
    $remaining = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($detached, $asset);
    galleryFixturePrimary($remaining, $asset);

    app(ArtworkGalleryAssignmentService::class)->detach($detached);

    expect($detached->fresh()->artwork_category_id)->toBeNull()
        ->and(Artwork::query()->whereKey($detached->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $detached->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and((int) $remaining->fresh()->position)->toBe(0);
});


it('deletes only the draft and its usages while preserving a shared MediaAsset', function (): void {
    $gallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $deleted = galleryFixtureArtwork($gallery, ['position' => 0]);
    $other = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($deleted, $asset);
    galleryFixturePrimary($other, $asset);

    app(ArtworkDraftService::class)->delete($deleted);

    expect(Artwork::query()->whereKey($deleted->getKey())->exists())->toBeFalse()
        ->and(ArtworkMedia::query()->where('artwork_id', $deleted->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $other->getKey())->where('media_asset_id', $asset->getKey())->exists())->toBeTrue();
});


it('preserves replaced MediaAssets in the library even when they become unreferenced', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory());
    $oldAsset = galleryFixtureAsset();
    $newAsset = galleryFixtureAsset();
    galleryFixturePrimary($artwork, $oldAsset);

    app(ArtworkPrimaryMediaService::class)->replaceAsset($artwork, $newAsset);

    expect(MediaAsset::query()->whereKey($oldAsset->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($newAsset->getKey())->exists())->toBeTrue()
        ->and((int) ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->value('media_asset_id'))
        ->toBe((int) $newAsset->getKey());
});


it('accepts existing image and video MediaAssets as primary and rejects audio', function (): void {
    $service = app(ArtworkPrimaryMediaService::class);

    $imageArtwork = galleryFixtureArtwork(galleryFixtureCategory());
    $image = galleryFixtureAsset('image/jpeg');
    $service->attachAsset($imageArtwork, $image);

    $videoArtwork = galleryFixtureArtwork(galleryFixtureCategory());
    $video = galleryFixtureAsset('video/mp4');
    $service->attachAsset($videoArtwork, $video);

    $audioArtwork = galleryFixtureArtwork(galleryFixtureCategory());
    $audio = galleryFixtureAsset('audio/mpeg');

    expect((int) $imageArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $image->getKey())
        ->and((int) $videoArtwork->fresh()->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $video->getKey())
        ->and(fn () => $service->attachAsset($audioArtwork, $audio))->toThrow(ValidationException::class);
});


it('deletes the selected Artwork and detaches other Galleries when its shared primary file is explicitly deleted', function (): void {
    $source = galleryFixtureCategory();
    $otherGallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $deletedFromSource = galleryFixtureArtwork($source, ['position' => 0]);
    $remainingInSource = galleryFixtureArtwork($source, ['position' => 1]);
    $alsoAffected = galleryFixtureArtwork($otherGallery, ['position' => 0]);
    galleryFixturePrimary($deletedFromSource, $asset);
    galleryFixturePrimary($alsoAffected, $asset);
    galleryFixtureUnavailableAnalytics();
    $disk = Storage::disk(config('media.disk'));
    $disk->put($asset->storage_key, 'Shared original fixture');
    expect($disk->exists($asset->storage_key))->toBeTrue();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $source->getKey()])
        ->mountAction('deleteArtwork', ['artwork' => $deletedFromSource->getKey()])
        ->fillForm(['delete_media_file' => true])
        ->callMountedAction();

    expect(Artwork::query()->whereKey($deletedFromSource->getKey())->exists())->toBeFalse()
        ->and($alsoAffected->fresh()->getAttribute('artwork_category_id'))->toBeNull()
        ->and((int) $remainingInSource->fresh()->position)->toBe(0)
        ->and(ArtworkMedia::query()->where('media_asset_id', $asset->getKey())->exists())->toBeFalse()
        ->and($asset->fresh()->getAttribute('state'))->toBe('deleted')
        ->and($disk->exists($asset->storage_key))->toBeFalse();
});


it('cleans Gallery membership when a shared primary file is deleted directly from Storage', function (): void {
    $firstGallery = galleryFixtureCategory();
    $secondGallery = galleryFixtureCategory();
    $shared = galleryFixtureAsset();
    $other = galleryFixtureAsset();
    $first = galleryFixtureArtwork($firstGallery, ['position' => 0]);
    $kept = galleryFixtureArtwork($firstGallery, ['position' => 1]);
    $second = galleryFixtureArtwork($secondGallery, ['position' => 0]);
    galleryFixturePrimary($first, $shared);
    galleryFixturePrimary($second, $shared);
    galleryFixturePrimary($kept, $other);
    $sharedThumbnail = galleryFixtureThumbnail($shared);
    $otherThumbnail = galleryFixtureThumbnail($other);
    $disk = Storage::disk(config('media.disk'));
    foreach ([$shared, $other, $sharedThumbnail, $otherThumbnail] as $file) {
        $disk->put($file->storage_key, 'Media lifecycle fixture');
        expect($disk->exists($file->storage_key))->toBeTrue();
    }

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
        ->and($other->fresh()->state)->toBe('available')
        ->and($disk->exists($shared->storage_key))->toBeFalse()
        ->and($disk->exists($sharedThumbnail->storage_key))->toBeFalse()
        ->and($disk->exists($other->storage_key))->toBeTrue()
        ->and($disk->exists($otherThumbnail->storage_key))->toBeTrue();

    $this->get(route('preview.site.section', ['section' => $firstGallery->slug]))
        ->assertOk()
        ->assertSee($kept->title)
        ->assertDontSee($first->title);
});


it('removes a selected batch from its Gallery without deleting artwork records or shared MediaAssets', function (): void {
    $gallery = galleryFixtureCategory();
    $shared = galleryFixtureAsset();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    $second = galleryFixtureArtwork($gallery, ['position' => 1]);
    $remaining = galleryFixtureArtwork($gallery, ['position' => 2]);
    galleryFixturePrimary($first, $shared);
    galleryFixturePrimary($second, $shared);
    galleryFixturePrimary($remaining, $shared);
    galleryFixtureUnavailableAnalytics();
    $selected = [$second->getKey(), $first->getKey()];
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('removeSelectedArtworks')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('selectedArtworkIds', [])
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect($first->fresh()->artwork_category_id)->toBeNull()
        ->and($second->fresh()->artwork_category_id)->toBeNull()
        ->and((int) $remaining->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and((int) $remaining->fresh()->position)->toBe(0)
        ->and(ArtworkMedia::query()->where('media_asset_id', $shared->getKey())->count())->toBe(3)
        ->and($shared->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->whereIn('entity_id', $selected)->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('deletes only selected draft artworks and their usages without deleting Storage media', function (): void {
    $gallery = galleryFixtureCategory();
    $shared = galleryFixtureAsset();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    $second = galleryFixtureArtwork($gallery, ['position' => 1]);
    $remaining = galleryFixtureArtwork($gallery, ['position' => 2]);
    galleryFixturePrimary($first, $shared);
    galleryFixturePrimary($second, $shared);
    galleryFixturePrimary($remaining, $shared);
    galleryFixtureUnavailableAnalytics();
    $selected = [$first->getKey(), $second->getKey()];
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('deleteSelectedArtworks')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('selectedArtworkIds', [])
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect(Artwork::query()->whereKey($first->getKey())->exists())->toBeFalse()
        ->and(Artwork::query()->whereKey($second->getKey())->exists())->toBeFalse()
        ->and(ArtworkMedia::query()->whereIn('artwork_id', $selected)->exists())->toBeFalse()
        ->and(Artwork::query()->whereKey($remaining->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $remaining->getKey())
            ->where('media_asset_id', $shared->getKey())->exists())->toBeTrue()
        ->and($shared->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'artwork.deleted')->whereIn('entity_id', $selected)->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('rolls back selected Gallery removal and deletion when one selected artwork is published', function (string $action): void {
    $gallery = galleryFixtureCategory();
    $draft = galleryFixtureArtwork($gallery, ['position' => 0]);
    $published = galleryFixtureArtwork($gallery, ['position' => 1]);
    $shared = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($draft, $shared);
    galleryFixturePrimary($published, $shared);
    app(ArtworkPublicationService::class)->publish($published);
    galleryFixtureUnavailableAnalytics();

    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', [$draft->getKey(), $published->getKey()])
        ->mountAction($action)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect(Artwork::query()->whereKey($draft->getKey())->exists())->toBeTrue()
        ->and(Artwork::query()->whereKey($published->getKey())->exists())->toBeTrue()
        ->and((int) $draft->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and((int) $published->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and((int) $draft->fresh()->position)->toBe(0)
        ->and((int) $published->fresh()->position)->toBe(1)
        ->and(ArtworkMedia::query()->where('artwork_id', $draft->getKey())->where('media_asset_id', $shared->getKey())->exists())->toBeTrue()
        ->and(ArtworkMedia::query()->where('artwork_id', $published->getKey())->where('media_asset_id', $shared->getKey())->exists())->toBeTrue()
        ->and($shared->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(AdminNotification::query()->latest('id')->value('status'))->toBe('danger');
})->with(['removeSelectedArtworks', 'deleteSelectedArtworks']);
