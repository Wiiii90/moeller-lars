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

it('offers per-artwork ALT text in the Gallery create and edit dialogs without changing the shared file', function (): void {
    $gallery = polishGallery();
    $asset = polishAsset('image/jpeg', '');
    polishThumbnail($asset);
    polishUnavailableGalleryAnalytics();

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



it('repairs an existing media-less Gallery draft using the same Edit form and then publishes it', function (): void {
    $gallery = polishGallery();
    $draft = polishArtwork($gallery);
    $asset = polishAsset('image/jpeg', '');
    polishThumbnail($asset);
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
    $gallery = polishGallery();
    $asset = polishAsset('image/jpeg', '');
    polishThumbnail($asset);
    polishUnavailableGalleryAnalytics();

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
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $asset = polishAsset('image/jpeg', 'Storage-level description');
    polishThumbnail($asset);
    polishPrimary($artwork, $asset);
    polishUnavailableGalleryAnalytics();

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
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $asset = polishAsset('image/jpeg', '');
    polishThumbnail($asset);
    polishPrimary($artwork, $asset);
    polishUnavailableGalleryAnalytics();

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
