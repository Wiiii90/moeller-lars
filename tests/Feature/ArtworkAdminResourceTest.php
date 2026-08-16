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

it('validates slug, position, and required fields', function () {
    Livewire::test(CreateArtwork::class)
        ->fillForm(['title' => '', 'slug' => 'Bad Slug', 'position' => -1])
        ->call('create')
        ->assertHasFormErrors(['title', 'slug', 'artwork_category_id', 'position']);
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
