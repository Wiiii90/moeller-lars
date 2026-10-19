<?php

use App\Domain\Artwork\ArtworkPublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('offers per-artwork ALT text in the Gallery create and edit dialogs without changing the shared file', function (): void {
    $gallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('addArtwork')
        ->fillForm([
            'title' => 'New publishable draft',
            'slug' => 'new-publishable-draft',
            'work_date' => null,
            'dimension_unit' => 'cm',
            'primary_media_asset_id' => $asset->getKey(),
            'primary_alt_text' => 'Painted landscape with bright green hills',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('slug', 'new-publishable-draft')->firstOrFail();
    expect($artwork->state)->toBe('draft')
        ->and($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))
            ->toBe('Painted landscape with bright green hills');

    expect(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});



it('clears a draft primary Media File from Edit without deleting the shared Storage original', function (): void {
    $gallery = galleryFixtureCategory();
    $draft = galleryFixtureArtwork($gallery);
    $shared = galleryFixtureAsset();
    galleryFixtureThumbnail($shared);
    galleryFixturePrimary($draft, $shared);
    galleryFixtureUnavailableAnalytics();

    $form = app(ArtworkEditDialog::class)->fill($draft);
    $form['primary_media_asset_id'] = null;
    $form['primary_alt_text'] = null;

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('editArtwork', ['artwork' => $draft->getKey()])
        ->fillForm($form)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect(ArtworkMedia::query()->where('artwork_id', $draft->getKey())->where('role', 'primary')->exists())->toBeFalse()
        ->and((string) $shared->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_detached')->where('entity_id', $draft->getKey())->count())->toBe(1)
        ->and(AdminNotification::query()->where('title', 'Artwork saved')->count())->toBe(1);
});

it('refuses to clear primary media from a published Artwork without touching the Storage original', function (): void {
    $gallery = galleryFixtureCategory();
    $draft = galleryFixtureArtwork($gallery);
    $shared = galleryFixtureAsset();
    galleryFixtureThumbnail($shared);
    galleryFixturePrimary($draft, $shared);
    app(ArtworkPublicationService::class)->publish($draft);

    $form = app(ArtworkEditDialog::class)->fill($draft);
    $form['primary_media_asset_id'] = null;
    $form['primary_alt_text'] = null;

    expect(fn () => app(ArtworkEditDialog::class)->save($draft, $form))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(ArtworkMedia::query()->where('artwork_id', $draft->getKey())->where('role', 'primary')->exists())->toBeTrue()
        ->and((string) $shared->fresh()->state)->toBe('available');
});

it('repairs an existing media-less Gallery draft using the same Edit form and then publishes it', function (): void {
    $gallery = galleryFixtureCategory();
    $draft = galleryFixtureArtwork($gallery);
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    $dialog = app(ArtworkEditDialog::class);

    $form = $dialog->fill($draft);
    expect($form['primary_media_asset_id'])->toBeNull();
    $form['primary_media_asset_id'] = $asset->getKey();
    $form['primary_alt_text'] = 'Abstract oil painting in warm ochre and blue';

    expect($dialog->save($draft, $form))->toBeTrue()
        ->and((int) $draft->artworkMedia()->where('role', 'primary')->value('media_asset_id'))
            ->toBe((int) $asset->getKey());

    expect(app(ArtworkPublicationService::class)->publish($draft)->state)->toBe('published');
});


it('stores per-artwork ALT for all images in the existing batch direct-upload action', function (): void {
    $gallery = galleryFixtureCategory();
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    galleryFixtureUnavailableAnalytics();

    $row = ['media_asset_id' => (int) $asset->getKey(), 'title' => 'A Batch Artwork'];
    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->set('pendingBatchArtworkMedia', [$row])
        ->mountAction('batchAddArtworks')
        ->fillForm([
            'artworks' => [[
                ...$row,
                'primary_alt_text' => 'A painting with overlapping tree branches',
            ]],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('title', 'A Batch Artwork')->firstOrFail();
    expect($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))
        ->toBe('A painting with overlapping tree branches');
    expect(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});

it('edits the ALT description through the Gallery Edit action while retaining shared Storage ALT', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $asset = galleryFixtureAsset('image/jpeg', 'Storage-level description');
    galleryFixtureThumbnail($asset);
    galleryFixturePrimary($artwork, $asset);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('editArtwork', ['artwork' => $artwork->getKey()])
        ->fillForm([
            ...app(ArtworkEditDialog::class)->fill($artwork),
            'primary_alt_text' => 'A ceramic sculpture beside a dark wall',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))
        ->toBe('A ceramic sculpture beside a dark wall')
        ->and($asset->fresh()->alt_text)->toBe('Storage-level description')
        ->and(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});


it('routes the Artwork EditRecord page through the same ALT-aware editing service', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery);
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    galleryFixturePrimary($artwork, $asset);
    galleryFixtureUnavailableAnalytics();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->fillForm([
            ...app(ArtworkEditDialog::class)->fill($artwork),
            'primary_alt_text' => 'A landscape painting viewed from a rocky terrace',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($artwork->artworkMedia()->where('role', 'primary')->value('alt_text_override'))
        ->toBe('A landscape painting viewed from a rocky terrace')
        ->and(app(ArtworkPublicationService::class)->publish($artwork)->state)->toBe('published');
});



it('renames artwork through the Gallery inline editor with one audit and notification, but stays silent on no-op', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery, ['title' => 'Original artwork title']);
    galleryFixtureUnavailableAnalytics();

    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->call('renameArtwork', $artwork->getKey(), '  Revised   artwork title  ')
        ->assertHasNoErrors()
        ->assertDispatched('admin-header-notification');

    expect($artwork->fresh()->title)->toBe('Revised artwork title')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AuditEvent::query()->latest('id')->value('action'))->toBe('artwork.updated')
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->call('renameArtwork', $artwork->getKey(), ' Revised artwork title ')
        ->assertHasNoErrors()
        ->assertNotDispatched('admin-header-notification');

    expect($artwork->fresh()->title)->toBe('Revised artwork title')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('does not rename artwork belonging to another Gallery through the inline editor', function (): void {
    $gallery = galleryFixtureCategory();
    $otherGallery = galleryFixtureCategory();
    $otherArtwork = galleryFixtureArtwork($otherGallery, ['title' => 'Owned by another Gallery']);
    galleryFixtureUnavailableAnalytics();

    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->call('renameArtwork', $otherArtwork->getKey(), 'Unexpected change')
        ->assertHasNoErrors()
        ->assertNotDispatched('admin-header-notification');

    expect($otherArtwork->fresh()->title)->toBe('Owned by another Gallery')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
});


it('opens the shared Add and Edit artwork forms with the canonical dialog lifecycle', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $artwork = galleryFixtureArtwork($gallery);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('addArtwork')
        ->assertMountedActionModalSee('Add artwork');

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('editArtwork', ['artwork' => $artwork->getKey()])
        ->assertMountedActionModalSee('Edit artwork');
});
