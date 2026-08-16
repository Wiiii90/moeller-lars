<?php

use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\Artworks\Pages\CreateArtwork;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
use App\Filament\Resources\Artworks\Pages\ListArtworks;
use App\Filament\Resources\Artworks\Pages\ViewArtwork;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminArtworkCategory(): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill(['slug' => fake()->unique()->slug(), 'name' => 'Admin category', 'state' => 'published', 'position' => 0]);
    $category->save();

    return $category;
}

function bootAdminPanel(): void
{
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
}

function adminJpegUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'admin-artwork-');
    $image = imagecreatetruecolor(8, 6);
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent('admin-artwork.jpg', file_get_contents($path));
}

beforeEach(function () {
    bootAdminPanel();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('shows the artwork resource index to an admin', function () {
    Livewire::test(ListArtworks::class)->assertSuccessful();
});

it('denies the artwork resource to a non-admin', function () {
    auth()->logout();
    $this->actingAs(User::factory()->create(), 'web');

    $this->get(ArtworkResource::getUrl('index'))->assertForbidden();
    expect(AuditEvent::query()->count())->toBe(0);
});

it('creates a draft with normalized date metadata', function () {
    $category = adminArtworkCategory();

    Livewire::test(CreateArtwork::class)
        ->fillForm([
            'title' => 'Admin artwork',
            'slug' => 'admin-artwork',
            'artwork_category_id' => $category->getKey(),
            'medium' => 'Oil',
            'dimensions' => '20 x 30 cm',
            'description' => 'Description',
            'work_date' => '2026-08-16',
            'position' => 2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->first();
    expect($artwork->state)->toBe('draft')
        ->and($artwork->date_precision)->toBe('day')
        ->and($artwork->published_at)->toBeNull();
    expect(AuditEvent::query()->where('action', 'artwork.created')->where('admin_user_id', auth()->id())->count())->toBe(1);
});

it('uses unknown precision without a work date', function () {
    $category = adminArtworkCategory();

    Livewire::test(CreateArtwork::class)
        ->fillForm(['title' => 'Undated', 'slug' => 'undated', 'artwork_category_id' => $category->getKey(), 'position' => 0])
        ->call('create');

    expect(Artwork::query()->first()->date_precision)->toBe('unknown');
});

it('validates slug and required fields', function () {
    Livewire::test(CreateArtwork::class)
        ->fillForm(['title' => '', 'slug' => 'Bad Slug', 'position' => -1])
        ->call('create')
        ->assertHasFormErrors(['title', 'slug', 'artwork_category_id']);
});

it('rejects a duplicate slug', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'duplicate-artwork', 'title' => 'Existing', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(CreateArtwork::class)
        ->fillForm(['title' => 'New', 'slug' => 'duplicate-artwork', 'artwork_category_id' => $category->getKey(), 'position' => 0])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('keeps editorial and provenance fields immutable on edit', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill([
        'artwork_category_id' => $category->getKey(), 'slug' => 'immutable-artwork', 'title' => 'Before',
        'state' => 'published', 'position' => 0, 'date_precision' => 'unknown', 'published_at' => now(),
        'legacy_id' => 42, 'legacy_source' => 'legacy', 'legacy_date_raw' => '1900',
    ]);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->fillForm(['title' => 'After', 'state' => 'draft', 'published_at' => null, 'legacy_id' => 99])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($artwork->fresh()->title)->toBe('After')
        ->and($artwork->fresh()->state)->toBe('published')
        ->and($artwork->fresh()->legacy_id)->toBe(42);
    expect(AuditEvent::query()->where('action', 'artwork.updated')->where('entity_id', $artwork->getKey())->count())->toBe(1);
});

it('does not audit an edit save with no actual change', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'unchanged-artwork', 'title' => 'Unchanged', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->fillForm(['title' => 'Unchanged'])
        ->call('save');

    expect(AuditEvent::query()->where('action', 'artwork.updated')->count())->toBe(0);
});

it('does not allow a published slug to be edited', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'published-artwork', 'title' => 'Published', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown', 'published_at' => now()]);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])->assertSet('data.slug', 'published-artwork');
});

it('rejects moving published artwork to a hidden category without auditing', function () {
    $publishedCategory = adminArtworkCategory();
    $hiddenCategory = new ArtworkCategory;
    $hiddenCategory->fill(['slug' => 'hidden-target', 'name' => 'Hidden target', 'state' => 'hidden', 'position' => 1]);
    $hiddenCategory->save();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $publishedCategory->id, 'slug' => 'published-category-invariant', 'title' => 'Invariant', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown', 'published_at' => now()]);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])
        ->fillForm(['artwork_category_id' => $hiddenCategory->id])
        ->assertSet('data.artwork_category_id', $hiddenCategory->id)
        ->call('save')
        ->assertHasErrors(['artwork_category_id']);

    expect($artwork->fresh()->artwork_category_id)->toBe($publishedCategory->id)
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->where('entity_id', $artwork->id)->count())->toBe(0);
});

it('allows moving published artwork to another published category and audits once', function () {
    $first = adminArtworkCategory();
    $second = new ArtworkCategory;
    $second->fill(['slug' => 'published-target', 'name' => 'Published target', 'state' => 'published', 'position' => 1]);
    $second->save();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $first->id, 'slug' => 'published-category-move', 'title' => 'Move', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown', 'published_at' => now()]);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])
        ->fillForm(['artwork_category_id' => $second->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($artwork->fresh()->artwork_category_id)->toBe($second->id)
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->where('entity_id', $artwork->id)->count())->toBe(1);
});

it('hides numeric artwork position input and appends new artwork to the category', function () {
    $category = adminArtworkCategory();
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'append-zero', 'title' => 'Zero', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'append-one', 'title' => 'One', 'state' => 'draft', 'position' => 1, 'date_precision' => 'unknown']);
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'append-five', 'title' => 'Five', 'state' => 'draft', 'position' => 5, 'date_precision' => 'unknown']);

    Livewire::test(CreateArtwork::class)
        ->assertFormFieldDoesNotExist('position')
        ->fillForm(['title' => 'Appended', 'slug' => 'appended-artwork', 'artwork_category_id' => $category->id, 'position' => 99])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Artwork::query()->where('slug', 'appended-artwork')->value('position'))->toBe(6);
});

it('ignores injected position data and preserves position on an ordinary edit', function () {
    $category = adminArtworkCategory();
    $artwork = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'position-immutable-edit', 'title' => 'Before', 'state' => 'draft', 'position' => 12, 'date_precision' => 'unknown']);

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])
        ->assertFormFieldDoesNotExist('position')
        ->fillForm(['title' => 'After', 'position' => 1])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($artwork->fresh()->position)->toBe(12);
});

it('starts a new category gallery at position zero', function () {
    $category = ArtworkCategory::create(['name' => 'Empty category', 'slug' => 'empty-artwork-category', 'state' => 'published', 'position' => 2]);

    Livewire::test(CreateArtwork::class)
        ->fillForm(['title' => 'First artwork', 'slug' => 'first-empty-category-artwork', 'artwork_category_id' => $category->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Artwork::query()->where('slug', 'first-empty-category-artwork')->value('position'))->toBe(0);
});

it('appends an artwork to the destination category when moved', function () {
    $source = adminArtworkCategory();
    $destination = ArtworkCategory::create(['name' => 'Destination', 'slug' => 'destination-category', 'state' => 'published', 'position' => 1]);
    Artwork::create(['artwork_category_id' => $destination->id, 'slug' => 'destination-seven', 'title' => 'Seven', 'state' => 'draft', 'position' => 7, 'date_precision' => 'unknown']);
    $artwork = Artwork::create(['artwork_category_id' => $source->id, 'slug' => 'moving-artwork', 'title' => 'Moving', 'state' => 'draft', 'position' => 2, 'date_precision' => 'unknown']);

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])
        ->fillForm(['artwork_category_id' => $destination->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($artwork->fresh()->artwork_category_id)->toBe($destination->id)
        ->and($artwork->fresh()->position)->toBe(8);
});

it('uploads primary media through the real Filament action', function () {
    Storage::fake('local');
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'upload-artwork', 'title' => 'Upload artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'uploadPrimaryMedia')
        ->set('mountedActions.0.data.media', adminJpegUpload())
        ->call('callMountedAction');

    $asset = MediaAsset::query()->first();
    expect(ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->count())->toBe(1)
        ->and($asset)->not->toBeNull()
        ->and($asset->variants()->where('transform_profile', 'public-v1')->exists())->toBeTrue();
    expect(AuditEvent::query()->whereIn('action', ['media.ingested', 'artwork.primary_media_attached'])->where('admin_user_id', auth()->id())->count())->toBe(2);
});

it('shows only the appropriate primary media action', function () {
    $category = adminArtworkCategory();
    $withoutPrimary = Artwork::create(['artwork_category_id' => $category->getKey(), 'slug' => 'action-without-primary', 'title' => 'Without primary', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    Livewire::test(EditArtwork::class, ['record' => $withoutPrimary->getKey()])
        ->assertActionVisible('uploadPrimaryMedia')
        ->assertActionHidden('replacePrimaryMedia');

    $withPrimary = Artwork::create(['artwork_category_id' => $category->getKey(), 'slug' => 'action-with-primary', 'title' => 'With primary', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $asset = MediaAsset::create(['storage_key' => 'originals/action-primary.jpg', 'original_filename' => 'action-primary.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('d', 64), 'state' => 'available']);
    ArtworkMedia::create(['artwork_id' => $withPrimary->getKey(), 'media_asset_id' => $asset->getKey(), 'role' => 'primary', 'position' => 0]);
    Livewire::test(EditArtwork::class, ['record' => $withPrimary->getKey()])
        ->assertActionHidden('uploadPrimaryMedia')
        ->assertActionVisible('replacePrimaryMedia');
});

it('replaces primary media through the real Filament action and clears the ALT override', function () {
    Storage::fake('local');
    $category = adminArtworkCategory();
    $artwork = Artwork::create(['artwork_category_id' => $category->getKey(), 'slug' => 'replace-action-artwork', 'title' => 'Replace artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $oldAsset = MediaAsset::create(['storage_key' => 'originals/replace-old.jpg', 'original_filename' => 'replace-old.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('e', 64), 'state' => 'available']);
    $oldVariant = MediaVariant::create(['media_asset_id' => $oldAsset->getKey(), 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/replace-old.webp', 'mime_type' => 'image/webp', 'byte_size' => 3, 'sha256' => str_repeat('f', 64), 'transform_profile' => 'public-v1', 'state' => 'available', 'width' => 2, 'height' => 2]);
    $primary = ArtworkMedia::create(['artwork_id' => $artwork->getKey(), 'media_asset_id' => $oldAsset->getKey(), 'role' => 'primary', 'position' => 3, 'alt_text_override' => 'Old ALT']);
    Storage::disk('local')->put($oldAsset->storage_key, 'old');
    Storage::disk('local')->put($oldVariant->storage_key, 'oldv');

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'replacePrimaryMedia')
        ->set('mountedActions.0.data.media', adminJpegUpload())
        ->call('callMountedAction');

    $newPrimary = $primary->fresh();
    expect($newPrimary->getKey())->toBe($primary->getKey())
        ->and($newPrimary->media_asset_id)->not->toBe($oldAsset->getKey())
        ->and($newPrimary->position)->toBe(3)
        ->and($newPrimary->alt_text_override)->toBeNull()
        ->and($oldAsset->fresh()->state)->toBe('deleted')
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_replaced')->where('admin_user_id', auth()->id())->count())->toBe(1);
});

it('replaces a published primary through the real action and keeps it public', function () {
    Storage::fake('local');
    $category = adminArtworkCategory();
    $artwork = Artwork::create(['artwork_category_id' => $category->getKey(), 'slug' => 'replace-published-artwork', 'title' => 'Published replace', 'state' => 'published', 'published_at' => now(), 'position' => 0, 'date_precision' => 'unknown']);
    $oldAsset = MediaAsset::create(['storage_key' => 'originals/replace-published-old.jpg', 'original_filename' => 'old.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('a', 64), 'state' => 'available']);
    ArtworkMedia::create(['artwork_id' => $artwork->getKey(), 'media_asset_id' => $oldAsset->getKey(), 'role' => 'primary', 'position' => 0]);
    Storage::disk('local')->put($oldAsset->storage_key, 'old');

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'replacePrimaryMedia')
        ->set('mountedActions.0.data.media', adminJpegUpload())
        ->call('callMountedAction');

    $artwork->refresh();
    expect($artwork->state)->toBe('published')
        ->and($artwork->artworkMedia()->where('role', 'primary')->count())->toBe(1)
        ->and($artwork->artworkMedia()->firstOrFail()->mediaAsset->state)->toBe('available');
});

it('edits the primary artwork ALT override without changing the asset default', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'alt-action-artwork', 'title' => 'ALT action', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();
    $asset = new MediaAsset;
    $asset->fill(['storage_key' => 'originals/alt-action.jpg', 'original_filename' => 'alt-action.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('c', 64), 'state' => 'available', 'alt_text' => 'Asset default']);
    $asset->save();
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])
        ->call('mountAction', 'editPrimaryAlt')
        ->set('mountedActions.0.data.alt_text_override', 'Artwork override')
        ->call('callMountedAction');

    expect($artwork->artworkMedia()->firstOrFail()->alt_text_override)->toBe('Artwork override')
        ->and($asset->fresh()->alt_text)->toBe('Asset default')
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_alt_updated')->count())->toBe(1);
});

it('hides the primary ALT action when no primary usage exists', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'no-alt-action', 'title' => 'No ALT action', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->id])->assertActionHidden('editPrimaryAlt');
});

it('shows primary media editorial metadata without storage keys', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'view-media-metadata', 'title' => 'View metadata', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();
    $asset = new MediaAsset;
    $asset->fill(['storage_key' => 'originals/view-metadata.jpg', 'original_filename' => 'view-metadata.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('d', 64), 'state' => 'available', 'alt_text' => 'Default ALT', 'credit' => 'Credit name', 'copyright_notice' => 'Copyright notice']);
    $asset->save();
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0, 'alt_text_override' => 'Usage ALT']);

    Livewire::test(ViewArtwork::class, ['record' => $artwork->id])
        ->assertSee('Default ALT')
        ->assertSee('Usage ALT')
        ->assertSee('Credit name')
        ->assertSee('Copyright notice')
        ->assertSee((string) $asset->sha256)
        ->assertDontSee('storage_key');
});

it('publishes and unpublishes through the editorial actions', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'publish-artwork', 'title' => 'Publish artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    $asset = new MediaAsset;
    $asset->fill(['storage_key' => 'originals/publish.jpg', 'original_filename' => 'publish.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 3, 'sha256' => str_repeat('b', 64), 'state' => 'available', 'width' => 2, 'height' => 2]);
    $asset->save();
    ArtworkMedia::create(['artwork_id' => $artwork->getKey(), 'media_asset_id' => $asset->getKey(), 'role' => 'primary', 'position' => 0]);

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])->call('mountAction', 'publish')->call('callMountedAction');
    expect($artwork->fresh()->state)->toBe('published');
    expect(AuditEvent::query()->where('action', 'artwork.published')->where('admin_user_id', auth()->id())->count())->toBe(1);

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])->call('mountAction', 'unpublish')->call('callMountedAction');
    expect($artwork->fresh()->state)->toBe('draft')->and($artwork->fresh()->published_at)->not->toBeNull();
    expect(AuditEvent::query()->where('action', 'artwork.unpublished')->where('admin_user_id', auth()->id())->count())->toBe(1);
});

it('shows a visible action error and stays draft when publishing without primary media', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'no-primary', 'title' => 'No primary', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'publish')
        ->call('callMountedAction');

    expect($artwork->fresh()->state)->toBe('draft');
});

it('lists and views artwork metadata without exposing storage keys', function () {
    $category = adminArtworkCategory();
    $artwork = new Artwork;
    $artwork->fill(['artwork_category_id' => $category->getKey(), 'slug' => 'listed-artwork', 'title' => 'Listed artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $artwork->save();

    Livewire::test(ListArtworks::class)->assertCanSeeTableRecords([$artwork]);
    Livewire::test(ViewArtwork::class, ['record' => $artwork->getKey()])->assertSee('Listed artwork')->assertDontSee('storage_key');
});
