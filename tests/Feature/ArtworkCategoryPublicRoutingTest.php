<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves custom published categories and rejects hidden or unknown categories', function () {
    $hidden = ArtworkCategory::create(['name' => 'Hidden custom', 'slug' => 'hidden-custom', 'state' => 'hidden', 'position' => 0]);
    $published = ArtworkCategory::create(['name' => 'Published custom', 'slug' => 'published-custom', 'state' => 'published', 'position' => 0]);
    Artwork::create(['artwork_category_id' => $published->id, 'slug' => 'custom-public-work', 'title' => 'Custom public work', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown']);

    $this->get('/hidden-custom')->assertNotFound();
    $this->get('/published-custom')->assertSuccessful()->assertSee('Published custom')->assertSee('Custom public work');
    $this->get('/unknown-custom')->assertNotFound();
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/artworks/missing-work')->assertNotFound();
    $this->get('/media/original/999999')->assertNotFound();
    $this->get('/index.php')->assertNotFound();
});

it('redirects renamed application categories through the generic category lifecycle', function () {
    $category = ArtworkCategory::create(['name' => 'Sculptures', 'slug' => 'sculptures', 'state' => 'published', 'position' => 0]);
    $category->update(['slug' => 'works-a']);
    Redirect::create(['source_path' => '/sculptures', 'target_path' => '/works-a', 'status_code' => 301, 'enabled' => true, 'reason' => 'artwork_category_slug_change']);

    $this->get('/sculptures')->assertRedirect('/works-a')->assertStatus(301);
    $this->get('/works-a')->assertSuccessful();
});
