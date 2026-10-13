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

it('publishes browser-supported video primary media without requiring an image thumbnail', function (): void {
    $artwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);

    $published = app(ArtworkPublicationService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and(app(PublicMedia::class)->kind($published))->toBe('video');
});


it('still requires the canonical public thumbnail for image publication', function (): void {
    $artwork = polishArtwork(polishGallery());
    $image = polishAsset();
    polishPrimary($artwork, $image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image))->toBeFalse();

    polishThumbnail($image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image->fresh('variants')))->toBeTrue();
});


it('requires unpublish before detach and preserves the historical publication timestamp when unpublishing', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);

    $publication = app(ArtworkPublicationService::class);
    $published = $publication->publish($artwork);
    $publishedAt = $published->published_at?->toISOString();

    expect(fn () => app(ArtworkGalleryAssignmentService::class)->detach($published))->toThrow(ValidationException::class);

    $draft = $publication->unpublish($published);

    expect($draft->state)->toBe('draft')
        ->and($draft->published_at?->toISOString())->toBe($publishedAt);

    app(ArtworkGalleryAssignmentService::class)->detach($draft);
    expect($draft->fresh()->artwork_category_id)->toBeNull();
});


it('rejects audio publication even if an invalid primary usage was inserted outside the canonical service', function (): void {
    $artwork = polishArtwork(polishGallery());
    $audio = polishAsset('audio/mpeg');
    polishPrimary($artwork, $audio);

    $publication = app(ArtworkPublicationService::class);

    expect($publication->mediaReady($audio))->toBeFalse()
        ->and(fn () => $publication->publish($artwork))->toThrow(ValidationException::class);
});


it('renders a published video artwork through the public viewer with native controls and no autoplay', function (): void {
    $artwork = polishArtwork(polishGallery());
    $video = polishAsset('video/mp4');
    polishPrimary($artwork, $video);
    $published = app(ArtworkPublicationService::class)->publish($artwork);
    $actor = User::query()->findOrFail((int) auth('web')->id());
    app(PublicationService::class)->commit($actor);

    $response = $this->get(route('artworks.show', ['slug' => $published->getAttribute('slug')]));

    $response->assertOk()
        ->assertSee('data-viewer-kind="video"', false)
        ->assertSee('<video', false)
        ->assertSee('controls', false)
        ->assertDontSee('autoplay', false);
});



it('notifies only when a Gallery publication action actually changes the artwork state', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    polishPrimary($artwork, polishAsset('video/mp4'));
    $arguments = ['artwork' => $artwork->getKey()];
    $params = ['gallery' => $gallery->getKey()];

    Livewire::test(GalleryWorkspace::class, $params)
        ->mountAction('publishArtwork', $arguments)
        ->callMountedAction()
        ->assertDispatched('admin-header-notification');

    expect($artwork->fresh()->state)->toBe('published');
    $notificationCount = \App\Models\AdminNotification::query()->count();
    $auditCount = AuditEvent::query()->count();

    Livewire::test(GalleryWorkspace::class, $params)
        ->mountAction('publishArtwork', $arguments)
        ->callMountedAction()
        ->assertNotDispatched('admin-header-notification');

    expect(\App\Models\AdminNotification::query()->count())->toBe($notificationCount)
        ->and(AuditEvent::query()->count())->toBe($auditCount);

    Livewire::test(GalleryWorkspace::class, $params)
        ->mountAction('unpublishArtwork', $arguments)
        ->callMountedAction()
        ->assertDispatched('admin-header-notification');

    expect($artwork->fresh()->state)->toBe('draft');
    $notificationCount = \App\Models\AdminNotification::query()->count();
    $auditCount = AuditEvent::query()->count();

    Livewire::test(GalleryWorkspace::class, $params)
        ->mountAction('unpublishArtwork', $arguments)
        ->callMountedAction()
        ->assertNotDispatched('admin-header-notification');

    expect(\App\Models\AdminNotification::query()->count())->toBe($notificationCount)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});
