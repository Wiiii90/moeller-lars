<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\PublicMedia;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\Artwork;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
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
    Auth::guard('web')->logout();

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



it('publishes an entire ready Gallery selection once and leaves repeated publication as a no-op', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    $second = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($first, galleryFixtureAsset('video/mp4'));
    galleryFixturePrimary($second, galleryFixtureAsset('video/mp4'));
    galleryFixtureUnavailableAnalytics();

    $selected = [$first->getKey(), $second->getKey()];
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('publishSelectedArtworks')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect($first->fresh()->state)->toBe('published')
        ->and($second->fresh()->state)->toBe('published')
        ->and($first->fresh()->published_at)->not->toBeNull()
        ->and($second->fresh()->published_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'artwork.published')
            ->where('entity_type', 'artwork')
            ->whereIn('entity_id', $selected)->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2);

    // One batch action is one successful user mutation, irrespective of how
    // many individual artwork audits it records.
    expect(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('publishSelectedArtworks')
        ->callMountedAction()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});


it('unpublishes a selected batch while preserving each publication date and sending one success feedback', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    $second = galleryFixtureArtwork($gallery, ['position' => 1]);
    galleryFixturePrimary($first, galleryFixtureAsset('video/mp4'));
    galleryFixturePrimary($second, galleryFixtureAsset('video/mp4'));
    $publication = app(ArtworkPublicationService::class);
    $firstDate = $publication->publish($first)->published_at?->toISOString();
    $secondDate = $publication->publish($second)->published_at?->toISOString();
    app(AdminActivityNotificationContext::class)->discard();
    galleryFixtureUnavailableAnalytics();
    $selected = [$first->getKey(), $second->getKey()];
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('unpublishSelectedArtworks')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect($first->fresh()->state)->toBe('draft')
        ->and($second->fresh()->state)->toBe('draft')
        ->and($first->fresh()->published_at?->toISOString())->toBe($firstDate)
        ->and($second->fresh()->published_at?->toISOString())->toBe($secondDate)
        ->and(AuditEvent::query()->where('action', 'artwork.unpublished')->whereIn('entity_id', $selected)->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('unpublishSelectedArtworks')
        ->callMountedAction()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
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

it('retains the Gallery Artwork editor after a confirmed Unpublish launched from its header', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    galleryFixturePrimary($artwork, galleryFixtureAsset('video/mp4'));
    app(ArtworkPublicationService::class)->publish($artwork);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('editArtwork', ['artwork' => $artwork->getKey()])
        ->assertSet('mountedActions.0.name', 'editArtwork')
        ->mountAction('dialogArtworkPublication')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editArtwork')
        ->assertSet('mountedActions.0.arguments.artwork', $artwork->getKey())
        ->assertDispatched('admin-header-notification');

    expect($artwork->fresh()->state)->toBe('draft');

    // The standalone Unpublish row command is deliberately not an editor.
    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('unpublishArtwork', ['artwork' => $artwork->getKey()])
        ->callMountedAction()
        ->assertSet('mountedActions', []);
});
