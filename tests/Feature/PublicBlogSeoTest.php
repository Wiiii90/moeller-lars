<?php

use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the blog completely unavailable until enabled', function () {
    BlogPost::create([
        'slug' => 'hidden-post',
        'title' => 'Hidden post',
        'body' => 'Body',
        'state' => 'published',
        'position' => 0,
        'published_at' => now(),
    ]);

    $this->get('/blog')->assertNotFound();
    $this->get('/blog/hidden-post')->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee('/blog');
});

it('publishes enabled blog routes and excludes future scheduled posts', function () {
    BlogSetting::query()->findOrFail(1)->update(['public_enabled' => true]);
    BlogPost::create([
        'slug' => 'published-post', 'title' => 'Published post', 'body' => 'Safe **body**',
        'state' => 'published', 'position' => 0, 'published_at' => now(),
    ]);
    BlogPost::create([
        'slug' => 'future-post', 'title' => 'Future post', 'body' => 'Future body',
        'state' => 'scheduled', 'position' => 1, 'scheduled_at' => now()->addDay(),
    ]);

    $this->get('/blog')->assertSuccessful()->assertSee('Published post')->assertDontSee('Future post');
    $this->get('/blog/published-post')->assertSuccessful()->assertSee('<strong>body</strong>', false);
    $this->get('/blog/future-post')->assertNotFound();
});

it('builds sitemap from public feature state only', function () {
    ArtworkCategory::create(['slug' => 'sculptures', 'name' => 'Sculptures', 'state' => 'published', 'position' => 0]);
    PublicContentSetting::query()->findOrFail(1)->update(['cv_enabled' => true]);
    BlogSetting::query()->findOrFail(1)->update(['public_enabled' => true]);

    $response = $this->get('/sitemap.xml')->assertSuccessful();
    $response->assertSee('/sculptures')->assertSee('/cv')->assertSee('/blog')->assertDontSee('/contact');
});

it('publishes intentional robots policy', function () {
    $this->get('/robots.txt')
        ->assertSuccessful()
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap:');
});
