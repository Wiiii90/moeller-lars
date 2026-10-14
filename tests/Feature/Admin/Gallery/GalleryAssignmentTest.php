<?php

use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\PublicMedia;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\AdminNotification;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('moves an artwork between Galleries without mutating its MediaAsset', function (): void {
    $source = galleryFixtureCategory();
    $destination = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $moved = galleryFixtureArtwork($source, ['position' => 0]);
    $remaining = galleryFixtureArtwork($source, ['position' => 1]);
    galleryFixturePrimary($moved, $asset);

    app(ArtworkGalleryAssignmentService::class)->reassign($moved, $destination);

    expect((int) $moved->fresh()->artwork_category_id)->toBe((int) $destination->getKey())
        ->and((int) $remaining->fresh()->position)->toBe(0)
        ->and((int) ArtworkMedia::query()->where('artwork_id', $moved->getKey())->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});


it('moves an artwork through the Gallery workspace action without losing its destination or MediaAsset', function (): void {
    $source = galleryFixtureCategory();
    $destination = galleryFixtureCategory();
    $asset = galleryFixtureAsset();
    $moved = galleryFixtureArtwork($source, ['position' => 0]);
    $remaining = galleryFixtureArtwork($source, ['position' => 1]);
    galleryFixturePrimary($moved, $asset);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $source->getKey()])
        ->mountAction('moveArtworkToGallery', ['artwork' => $moved->getKey()])
        ->fillForm(['target_gallery_id' => $destination->getKey()])
        ->callMountedAction();

    $fresh = $moved->fresh('category');

    expect((int) $fresh->getAttribute('artwork_category_id'))->toBe((int) $destination->getKey())
        ->and((int) $fresh->getRelation('category')->getKey())->toBe((int) $destination->getKey())
        ->and(Artwork::query()->whereKey($moved->getKey())->where('artwork_category_id', $source->getKey())->exists())->toBeFalse()
        ->and(Artwork::query()->whereKey($moved->getKey())->where('artwork_category_id', $destination->getKey())->exists())->toBeTrue()
        ->and((int) ArtworkMedia::query()->where('artwork_id', $moved->getKey())->where('role', 'primary')->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and((int) $remaining->fresh()->position)->toBe(0);
});



it('moves a selected Gallery batch in source order while preserving media and emitting one action feedback', function (): void {
    $source = galleryFixtureCategory();
    $destination = galleryFixtureCategory();
    $first = galleryFixtureArtwork($source, ['position' => 0]);
    $second = galleryFixtureArtwork($source, ['position' => 1]);
    $remaining = galleryFixtureArtwork($source, ['position' => 2]);
    $existingDestination = galleryFixtureArtwork($destination, ['position' => 0]);
    $firstMedia = galleryFixtureAsset();
    $secondMedia = galleryFixtureAsset();
    galleryFixturePrimary($first, $firstMedia);
    galleryFixturePrimary($second, $secondMedia);
    galleryFixtureUnavailableAnalytics();

    $selected = [$second->getKey(), $first->getKey()];
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $source->getKey()])
        ->set('selectedArtworkIds', $selected)
        ->mountAction('moveSelectedToGallery')
        ->fillForm(['target_gallery_id' => $destination->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('selectedArtworkIds', [])
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect((int) $first->fresh()->artwork_category_id)->toBe((int) $destination->getKey())
        ->and((int) $second->fresh()->artwork_category_id)->toBe((int) $destination->getKey())
        ->and((int) $first->fresh()->position)->toBe(1)
        ->and((int) $second->fresh()->position)->toBe(2)
        ->and((int) $existingDestination->fresh()->position)->toBe(0)
        ->and((int) $remaining->fresh()->position)->toBe(0)
        ->and((int) ArtworkMedia::query()->where('artwork_id', $first->getKey())->value('media_asset_id'))->toBe((int) $firstMedia->getKey())
        ->and((int) ArtworkMedia::query()->where('artwork_id', $second->getKey())->value('media_asset_id'))->toBe((int) $secondMedia->getKey())
        ->and(MediaAsset::query()->whereKey($firstMedia->getKey())->exists())->toBeTrue()
        ->and(MediaAsset::query()->whereKey($secondMedia->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->whereIn('entity_id', $selected)->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('edits per-artwork ALT text and publishes an uploaded image without changing shared Storage metadata', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $image = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($image);
    galleryFixturePrimary($artwork, $image);
    $publication = app(ArtworkPublicationService::class);

    expect($publication->mediaReady($image))->toBeFalse();
    expect(fn () => $publication->publish($artwork))->toThrow(ValidationException::class);

    $dialog = app(ArtworkEditDialog::class);
    $form = $dialog->fill($artwork);
    expect($form['primary_alt_text'])->toBeNull();
    $form['primary_alt_text'] = 'Oil painting depicting figures under tangled branches';

    expect($dialog->save($artwork, $form))->toBeTrue();
    $auditsAfterSave = AuditEvent::query()->count();
    expect($dialog->save($artwork, $form))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditsAfterSave)
        ->and($image->fresh()->alt_text)->toBe('');

    $primary = $artwork->artworkMedia()->where('role', 'primary')->firstOrFail();
    expect($primary->alt_text_override)->toBe($form['primary_alt_text'])
        ->and($publication->mediaReady($image, $primary->alt_text_override))->toBeTrue();

    $published = $publication->publish($artwork);
    expect($published->state)->toBe('published')
        ->and(app(PublicMedia::class)->altText($published))->toBe($form['primary_alt_text']);

    $this->get(route('preview.site.section', ['section' => $gallery->slug]))
        ->assertOk()
        ->assertSee($artwork->title);
});

it('saves a combined Gallery artwork edit once and preserves no-op silence after reopening', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $image = galleryFixtureAsset();
    galleryFixtureThumbnail($image);
    galleryFixturePrimary($artwork, $image);
    galleryFixtureUnavailableAnalytics();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $arguments = ['artwork' => $artwork->getKey()];

    $component = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('editArtwork', $arguments)
        ->fillForm(['title' => 'Edited artwork', 'primary_alt_text' => 'An edited media description'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertActionMounted('editArtwork')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return ($params['notification']['status'] ?? null) === 'success';
        });

    expect(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1)
        ->and($artwork->fresh()->title)->toBe('Edited artwork')
        ->and($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))->toBe('An edited media description')
        ->and($image->fresh()->alt_text)->toBe('Artwork media')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $component->callMountedAction()->assertNotDispatched('admin-header-notification');
    $component->unmountAction()->mountAction('editArtwork', $arguments)
        ->assertActionDataSet(['title' => 'Edited artwork', 'primary_alt_text' => 'An edited media description'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});


it('edits an unassigned draft and reassigns it using the shared Artwork editor', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork(null);
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    galleryFixturePrimary($artwork, $asset);
    $dialog = app(ArtworkEditDialog::class);

    $form = $dialog->fill($artwork);
    expect($form['artwork_category_id'])->toBeNull();
    $form['artwork_category_id'] = $gallery->getKey();
    $form['primary_alt_text'] = 'A bronze statue in profile';

    expect($dialog->save($artwork, $form, allowGalleryAssignment: true))->toBeTrue()
        ->and((int) $artwork->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))
            ->toBe('A bronze statue in profile')
        ->and(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});


it('updates an unassigned artwork without silently attaching it to a Gallery', function (): void {
    $artwork = galleryFixtureArtwork(null);
    $dialog = app(ArtworkEditDialog::class);

    $form = $dialog->fill($artwork);
    $form['title'] = 'Updated unassigned draft';

    expect($dialog->save($artwork, $form, allowGalleryAssignment: true))->toBeTrue()
        ->and($artwork->fresh()->artwork_category_id)->toBeNull()
        ->and($artwork->fresh()->title)->toBe('Updated unassigned draft');
});


it('rejects detaching a published artwork through the shared editor without changing its metadata', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $asset = galleryFixtureAsset('image/jpeg', 'A monochrome photograph of a sculpture');
    galleryFixtureThumbnail($asset);
    galleryFixturePrimary($artwork, $asset);
    app(ArtworkPublicationService::class)->publish($artwork);

    $form = app(ArtworkEditDialog::class)->fill($artwork->fresh());
    $form['artwork_category_id'] = null;
    $form['title'] = 'Must not be saved';

    expect(fn () => app(ArtworkEditDialog::class)->save($artwork->fresh(), $form, allowGalleryAssignment: true))
        ->toThrow(ValidationException::class);

    expect((int) $artwork->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and($artwork->fresh()->title)->not->toBe('Must not be saved');
});



it('rejects a published artwork move to an unpublished Gallery without changing its membership or audit', function (): void {
    $source = galleryFixtureCategory('published');
    $destination = galleryFixtureCategory('hidden');
    $artwork = galleryFixtureArtwork($source);
    $asset = galleryFixtureAsset('video/mp4');
    galleryFixturePrimary($artwork, $asset);
    app(ArtworkPublicationService::class)->publish($artwork);
    $auditsBefore = AuditEvent::query()->count();

    expect(fn () => app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination))
        ->toThrow(ValidationException::class);

    expect((int) $artwork->fresh()->artwork_category_id)->toBe((int) $source->getKey())
        ->and($artwork->fresh()->state)->toBe('published')
        ->and((int) $artwork->artworkMedia()->where('role', 'primary')->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);
});

it('does not create an audit event when assigning an artwork to its current Gallery', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $auditsBefore = AuditEvent::query()->count();

    app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $gallery);

    expect((int) $artwork->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);
});



it('rolls back every selected Gallery move when a published artwork cannot enter the destination', function (): void {
    $source = galleryFixtureCategory('published');
    $destination = galleryFixtureCategory('hidden');
    $draft = galleryFixtureArtwork($source, ['position' => 0]);
    $published = galleryFixtureArtwork($source, ['position' => 1]);
    galleryFixturePrimary($published, galleryFixtureAsset('video/mp4'));
    app(ArtworkPublicationService::class)->publish($published);
    galleryFixtureUnavailableAnalytics();

    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $source->getKey()])
        ->set('selectedArtworkIds', [$draft->getKey(), $published->getKey()])
        ->mountAction('moveSelectedToGallery')
        ->fillForm(['target_gallery_id' => $destination->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect((int) $draft->fresh()->artwork_category_id)->toBe((int) $source->getKey())
        ->and((int) $published->fresh()->artwork_category_id)->toBe((int) $source->getKey())
        ->and($draft->fresh()->position)->toBe(0)
        ->and($published->fresh()->position)->toBe(1)
        ->and(Artwork::query()->where('artwork_category_id', $destination->getKey())->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(AdminNotification::query()->latest('id')->value('status'))->toBe('danger');
});
