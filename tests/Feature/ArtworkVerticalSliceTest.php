<?php

use App\Filament\Resources\Artworks\Pages\CreateArtwork;
use App\Filament\Resources\Artworks\Pages\EditArtwork;
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

function verticalSliceJpeg(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'vertical-slice-');
    $image = imagecreatetruecolor(12, 8);
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent('vertical-slice.jpg', file_get_contents($path));
}

it('runs the complete canonical artwork admin and public vertical slice', function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    Storage::fake(config('media.disk'));

    $category = ArtworkCategory::create([
        'slug' => 'sculptures',
        'name' => 'Sculptures',
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'show_on_home' => true,
    ]);
    testGallerySection($category, ['state' => 'published', 'show_in_navigation' => true]);

    Livewire::test(CreateArtwork::class)
        ->fillForm([
            'title' => 'Vertical Slice Artwork',
            'slug' => 'vertical-slice-artwork',
            'artwork_category_id' => $category->getKey(),
            'medium' => 'Oil',
            'dimensions' => '40 x 50 cm',
            'description' => 'Vertical slice description',
            'work_date' => '2026-08-16',
            'position' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $artwork = Artwork::query()->where('slug', 'vertical-slice-artwork')->firstOrFail();
    expect($artwork->state)->toBe('draft')
        ->and($artwork->published_at)->toBeNull()
        ->and($artwork->date_precision)->toBe('day');

    $this->get('/sculptures')->assertSuccessful()->assertDontSee('Vertical Slice Artwork');
    $this->get('/artworks/vertical-slice-artwork')->assertNotFound();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'uploadPrimaryMedia')
        ->set('mountedActions.0.data.media', verticalSliceJpeg())
        ->call('callMountedAction');

    $artwork->refresh();
    $media = ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->firstOrFail();
    $asset = MediaAsset::query()->findOrFail($media->media_asset_id);
    $variant = $asset->variants()->where('variant_kind', 'thumbnail')->where('transform_profile', 'public-v1')->firstOrFail();
    $asset->update(['alt_text' => 'Vertical Slice Artwork']);

    expect(ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('role', 'primary')->count())->toBe(1)
        ->and($asset->state)->toBe('available')
        ->and(Storage::disk(config('media.disk'))->exists($asset->storage_key))->toBeTrue()
        ->and($asset->variants()->where('transform_profile', 'public-v1')->count())->toBe(1)
        ->and(Storage::disk(config('media.disk'))->exists($variant->storage_key))->toBeTrue();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'publish')
        ->call('callMountedAction');

    $artwork->refresh();
    expect($artwork->state)->toBe('published')->and($artwork->published_at)->not->toBeNull();

    $this->get('/')->assertSuccessful()->assertSee('Vertical Slice Artwork');
    $this->get('/sculptures')->assertSuccessful()
        ->assertSee('Vertical Slice Artwork')
        ->assertSee('Oil')
        ->assertSee('40 x 50 cm')
        ->assertSee('Vertical slice description');
    $this->get('/artworks/vertical-slice-artwork')->assertSuccessful()->assertSee('Vertical Slice Artwork');
    $this->get(route('media.original', $asset))->assertSuccessful();
    $this->get(route('media.variant', $variant))->assertSuccessful();

    Livewire::test(EditArtwork::class, ['record' => $artwork->getKey()])
        ->call('mountAction', 'unpublish')
        ->call('callMountedAction');

    $artwork->refresh();
    expect($artwork->state)->toBe('draft')->and($artwork->published_at)->not->toBeNull();
    $this->get('/sculptures')->assertSuccessful()->assertDontSee('Vertical Slice Artwork');
    $this->get('/artworks/vertical-slice-artwork')->assertNotFound();
    $this->get(route('media.original', $asset))->assertNotFound();
    $this->get(route('media.variant', $variant))->assertNotFound();

    $events = AuditEvent::query()->where('entity_id', $artwork->getKey())->orWhere('metadata->artwork_id', $artwork->getKey())->get();
    expect($events->pluck('action')->sort()->values()->all())
        ->toBe(['artwork.created', 'artwork.primary_media_attached', 'artwork.published', 'artwork.unpublished', 'media.ingested'])
        ->and($events->every(fn (AuditEvent $event): bool => $event->admin_user_id === $admin->getKey()))->toBeTrue()
        ->and($events->every(fn (AuditEvent $event): bool => collect($event->metadata ?? [])->keys()->every(fn (string $key): bool => in_array($key, ['artwork_id', 'media_asset_id'], true))))->toBeTrue();
});
