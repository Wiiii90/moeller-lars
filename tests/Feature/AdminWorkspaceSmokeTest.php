<?php

use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Widgets\ArtistDashboard;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('renders the dashboard shell and central admin index and create surfaces', function () {
    $this->get('/admin')->assertSuccessful();

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
        StorageCapacity::getUrl(),
        BlogSettingResource::getUrl('edit', ['record' => 1]),
        PublicContentSettingResource::getUrl('edit', ['record' => 1]),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('renders the artist dashboard overview eagerly in the initial dashboard response', function () {
    expect(ArtistDashboard::isLazy())->toBeFalse();

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Website at a glance')
        ->assertSee('Traffic & engagement')
        ->assertSee('Needs attention')
        ->assertSee('Recent activity')
        ->assertSee('Storage headroom')
        ->assertDontSee('Content overview')
        ->assertDontSee('Galleries');
});

it('keeps public placement controls in Pages instead of legacy settings editors', function () {
    $category = ArtworkCategory::create([
        'name' => 'Placement category',
        'slug' => 'placement-category',
        'state' => 'hidden',
        'position' => 0,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);

    $this->get(ArtworkCategoryResource::getUrl('edit', ['record' => $category]))
        ->assertSuccessful()
        ->assertSee('Publication, menu placement, submenu parent and site order are managed from Pages.')
        ->assertSee('Eligible for homepage')
        ->assertDontSee('Parent category')
        ->assertDontSee('Show in public navigation');

    $this->get(BlogSettingResource::getUrl('edit', ['record' => 1]))
        ->assertSuccessful()
        ->assertSee('Public visibility, navigation and site order are managed from Pages.')
        ->assertDontSee('Publish blog section')
        ->assertDontSee('Navigation label');

    $this->get(PublicContentSettingResource::getUrl('edit', ['record' => 1]))
        ->assertSuccessful()
        ->assertSee('Vita and Exhibitions publication/navigation are managed from Pages.')
        ->assertDontSee('Publish Vita / CV section')
        ->assertDontSee('Publish exhibitions section');
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
