<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves custom published Galleries and rejects hidden or unknown Galleries', function () {
    $hidden = ArtworkCategory::create(['name' => 'Hidden custom', 'slug' => 'hidden-custom', 'show_on_home' => false]);
    testGallerySection($hidden, ['state' => 'hidden']);
    $published = ArtworkCategory::create(['name' => 'Published custom', 'slug' => 'published-custom', 'show_on_home' => false]);
    testGallerySection($published, ['state' => 'published']);
    $artwork = Artwork::create(['artwork_category_id' => $published->id, 'slug' => 'custom-public-work', 'title' => 'Custom public work', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown']);
    $asset = MediaAsset::create(['storage_key' => 'originals/custom.jpg', 'original_filename' => 'custom.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 4, 'sha256' => str_repeat('a', 64), 'state' => 'available', 'alt_text' => 'Custom public work']);
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);
    MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/custom.webp', 'mime_type' => 'image/webp', 'byte_size' => 4, 'sha256' => str_repeat('b', 64), 'transform_profile' => 'public-v1', 'state' => 'available']);

    $this->get('/hidden-custom')->assertNotFound();
    $this->get('/published-custom')->assertSuccessful()->assertSee('Published custom')->assertSee('Custom public work');
    $this->get('/unknown-custom')->assertNotFound();
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/artworks/missing-work')->assertNotFound();
    $this->get('/media/original/999999')->assertNotFound();
    $this->get('/index.php')->assertNotFound();
});

it('redirects renamed Galleries through the canonical content lifecycle', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $category = ArtworkCategory::create(['name' => 'Sculptures', 'slug' => 'sculptures', 'show_on_home' => false]);
    testGallerySection($category, ['state' => 'published']);

    app(ArtworkCategoryEditorialService::class)->changeSlug($category, 'works-a');

    expect(Redirect::query()->where('source_path', '/sculptures')->value('target_path'))->toBe('/works-a');
    $this->get('/sculptures')->assertRedirect('/works-a')->assertStatus(301);
    $this->get('/works-a')->assertSuccessful();
});
