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

it('moves an artwork between Galleries without mutating its MediaAsset', function (): void {
    $source = polishGallery();
    $destination = polishGallery();
    $asset = polishAsset();
    $moved = polishArtwork($source, ['position' => 0]);
    $remaining = polishArtwork($source, ['position' => 1]);
    polishPrimary($moved, $asset);

    app(ArtworkGalleryAssignmentService::class)->reassign($moved, $destination);

    expect((int) $moved->fresh()->artwork_category_id)->toBe((int) $destination->getKey())
        ->and((int) $remaining->fresh()->position)->toBe(0)
        ->and((int) ArtworkMedia::query()->where('artwork_id', $moved->getKey())->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});


it('moves an artwork through the Gallery workspace action without losing its destination or MediaAsset', function (): void {
    $source = polishGallery();
    $destination = polishGallery();
    $asset = polishAsset();
    $moved = polishArtwork($source, ['position' => 0]);
    $remaining = polishArtwork($source, ['position' => 1]);
    polishPrimary($moved, $asset);
    polishUnavailableGalleryAnalytics();

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


it('edits per-artwork ALT text and publishes an uploaded image without changing shared Storage metadata', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $image = polishAsset('image/jpeg', '');
    polishThumbnail($image);
    polishPrimary($artwork, $image);
    $publication = app(ArtworkPublicationService::class);

    expect($publication->mediaReady($image))->toBeFalse();
    expect(fn () => $publication->publish($artwork))->toThrow(ValidationException::class);

    $dialog = app(ArtworkEditDialog::class);
    $form = $dialog->fill($artwork);
    expect($form['primary_alt_text'])->toBeNull();
    $form['primary_alt_text'] = 'Oil painting depicting figures under tangled branches';

    expect($dialog->save($artwork, $form))->toBeTrue()
        ->and($dialog->save($artwork, $form))->toBeFalse()
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


it('edits an unassigned draft and reassigns it using the shared Artwork editor', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork(null);
    $asset = polishAsset('image/jpeg', '');
    polishThumbnail($asset);
    polishPrimary($artwork, $asset);
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
    $artwork = polishArtwork(null);
    $dialog = app(ArtworkEditDialog::class);

    $form = $dialog->fill($artwork);
    $form['title'] = 'Updated unassigned draft';

    expect($dialog->save($artwork, $form, allowGalleryAssignment: true))->toBeTrue()
        ->and($artwork->fresh()->artwork_category_id)->toBeNull()
        ->and($artwork->fresh()->title)->toBe('Updated unassigned draft');
});


it('rejects detaching a published artwork through the shared editor without changing its metadata', function (): void {
    $gallery = polishGallery();
    $artwork = polishArtwork($gallery);
    $asset = polishAsset('image/jpeg', 'A monochrome photograph of a sculpture');
    polishThumbnail($asset);
    polishPrimary($artwork, $asset);
    app(ArtworkPublicationService::class)->publish($artwork);

    $form = app(ArtworkEditDialog::class)->fill($artwork->fresh());
    $form['artwork_category_id'] = null;
    $form['title'] = 'Must not be saved';

    expect(fn () => app(ArtworkEditDialog::class)->save($artwork->fresh(), $form, allowGalleryAssignment: true))
        ->toThrow(ValidationException::class);

    expect((int) $artwork->fresh()->artwork_category_id)->toBe((int) $gallery->getKey())
        ->and($artwork->fresh()->title)->not->toBe('Must not be saved');
});
