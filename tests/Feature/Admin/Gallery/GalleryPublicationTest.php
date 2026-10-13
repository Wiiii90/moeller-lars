<?php

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\PublicMedia;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\Artwork;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/GalleryFixtures.php';

it('publishes browser-supported video primary media without requiring an image thumbnail', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory());
    $video = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($artwork, $video);

    $published = app(ArtworkPublicationService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and(app(PublicMedia::class)->kind($published))->toBe('video');
});


it('still requires the canonical public thumbnail for image publication', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory());
    $image = galleryFixtureAsset();
    galleryFixturePrimary($artwork, $image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image))->toBeFalse();

    galleryFixtureThumbnail($image);

    expect(app(ArtworkPublicationService::class)->mediaReady($image->fresh('variants')))->toBeTrue();
});


it('requires unpublish before detach and preserves the historical publication timestamp when unpublishing', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $video = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($artwork, $video);

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
    $artwork = galleryFixtureArtwork(galleryFixtureCategory());
    $audio = galleryFixtureAsset('audio/mpeg');
    galleryFixturePrimary($artwork, $audio);

    $publication = app(ArtworkPublicationService::class);

    expect($publication->mediaReady($audio))->toBeFalse()
        ->and(fn () => $publication->publish($artwork))->toThrow(ValidationException::class);
});


it('renders a published video artwork through the public viewer with native controls and no autoplay', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory());
    $video = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($artwork, $video);
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
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    galleryFixturePrimary($artwork, galleryFixtureAsset('video/mp4'));
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



it('rolls back a batch publication when one selected artwork is not publishable', function (): void {
    $gallery = galleryFixtureCategory();
    $ready = galleryFixtureArtwork($gallery, ['position' => 0]);
    $incomplete = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($ready, galleryFixtureAsset('video/mp4'));
    galleryFixtureUnavailableAnalytics();

    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', [$ready->getKey(), $incomplete->getKey()])
        ->mountAction('publishSelectedArtworks')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($ready->fresh()->state)->toBe('draft')
        ->and($incomplete->fresh()->state)->toBe('draft')
        ->and($ready->fresh()->published_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(AdminNotification::query()->latest('id')->value('status'))->toBe('danger');
});
