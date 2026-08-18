<?php

use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('renders the dashboard and central admin index and create surfaces', function () {
    $this->get('/admin')->assertSuccessful()->assertSee('Dashboard');

    foreach ([
        ArtworkResource::getUrl('index'),
        ArtworkResource::getUrl('create'),
        ArtworkCategoryResource::getUrl('index'),
        ArtworkCategoryResource::getUrl('create'),
        CvEntryResource::getUrl('index'),
        CvEntryResource::getUrl('create'),
        ExhibitionResource::getUrl('index'),
        ExhibitionResource::getUrl('create'),
        BlogPostResource::getUrl('index'),
        BlogPostResource::getUrl('create'),
        MediaAssetResource::getUrl('index'),
        BlogSettingResource::getUrl('edit', ['record' => 1]),
        PublicContentSettingResource::getUrl('edit', ['record' => 1]),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('renders representative edit surfaces with their editorial form schemas', function () {
    $category = ArtworkCategory::create([
        'name' => 'Smoke category',
        'slug' => 'smoke-category',
        'state' => 'hidden',
        'position' => 0,
    ]);

    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'smoke-artwork',
        'title' => 'Smoke artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $cvEntry = CvEntry::create([
        'section' => 'Biography',
        'title' => 'Smoke Vita entry',
        'year_text' => '2026',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'year',
    ]);

    $exhibition = Exhibition::create([
        'slug' => 'smoke-exhibition',
        'title' => 'Smoke exhibition',
        'date_text' => '2026',
        'state' => 'draft',
        'position' => 0,
    ]);

    $blogPost = BlogPost::create([
        'slug' => 'smoke-blog-post',
        'title' => 'Smoke blog post',
        'body' => 'Smoke body',
        'state' => 'draft',
        'position' => 0,
    ]);

    foreach ([
        ArtworkCategoryResource::getUrl('edit', ['record' => $category]),
        ArtworkResource::getUrl('edit', ['record' => $artwork]),
        CvEntryResource::getUrl('edit', ['record' => $cvEntry]),
        ExhibitionResource::getUrl('edit', ['record' => $exhibition]),
        BlogPostResource::getUrl('edit', ['record' => $blogPost]),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});
