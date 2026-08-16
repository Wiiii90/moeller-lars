<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps all nine bootstrap category URLs public', function () {
    foreach (['paintings', 'prints', 'drawings', 'cyanotype', 'bichromate', 'litho', 'photo', 'ignis', 'other'] as $slug) {
        $this->get('/'.$slug)->assertSuccessful();
    }
});

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
    $this->get('/index.php')->assertRedirect('/');
});

it('redirects old custom category paths directly to the newest slug', function () {
    $category = ArtworkCategory::create(['name' => 'Redirected', 'slug' => 'old-custom', 'state' => 'published', 'position' => 0]);
    Redirect::create(['source_path' => '/old-custom', 'target_path' => '/new-custom', 'status_code' => 301, 'enabled' => true, 'reason' => 'artwork_category_slug_change']);
    Redirect::create(['source_path' => '/new-custom', 'target_path' => '/newest-custom', 'status_code' => 301, 'enabled' => true, 'reason' => 'artwork_category_slug_change']);
    $category->update(['slug' => 'newest-custom']);
    Redirect::query()->where('source_path', '/old-custom')->update(['target_path' => '/newest-custom']);

    $this->get('/old-custom')->assertRedirect('/newest-custom')->assertStatus(301);
    $this->get('/new-custom')->assertRedirect('/newest-custom')->assertStatus(301);
});
