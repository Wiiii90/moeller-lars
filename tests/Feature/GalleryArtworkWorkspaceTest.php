<?php

use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\Artworks\Pages\ManageGalleryArtworks;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('opens a Gallery-scoped artwork workspace from Pages', function (): void {
    $admin = User::factory()->admin()->create();
    $gallery = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings-workspace',
        'state' => 'published',
        'position' => 210,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $otherGallery = ArtworkCategory::create([
        'name' => 'Sculptures',
        'slug' => 'sculptures-workspace',
        'state' => 'hidden',
        'position' => 220,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    Artwork::create([
        'artwork_category_id' => $gallery->id,
        'slug' => 'workspace-painting',
        'title' => 'Workspace Painting',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);
    Artwork::create([
        'artwork_category_id' => $otherGallery->id,
        'slug' => 'other-workspace-artwork',
        'title' => 'Other Workspace Artwork',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);

    $galleryUrl = ArtworkResource::getUrl('gallery', ['gallery' => $gallery->id]);
    $createUrl = ArtworkResource::getUrl('create', ['gallery' => $gallery->id]);

    $this->actingAs($admin, 'web')
        ->get($galleryUrl)
        ->assertSuccessful()
        ->assertSee('Paintings')
        ->assertSee('Workspace Painting')
        ->assertDontSee('Other Workspace Artwork')
        ->assertSee('Add artwork')
        ->assertSee($createUrl, false);

    $this->get(SitePages::getUrl())
        ->assertSuccessful()
        ->assertSee($galleryUrl, false);
});

it('reorders artworks through the Gallery workspace without affecting another Gallery', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $gallery = ArtworkCategory::create([
        'name' => 'Ordered Gallery',
        'slug' => 'ordered-gallery',
        'state' => 'hidden',
        'position' => 230,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $otherGallery = ArtworkCategory::create([
        'name' => 'Other Gallery',
        'slug' => 'other-gallery-order',
        'state' => 'hidden',
        'position' => 240,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $first = Artwork::create([
        'artwork_category_id' => $gallery->id,
        'slug' => 'order-first',
        'title' => 'First',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);
    $second = Artwork::create([
        'artwork_category_id' => $gallery->id,
        'slug' => 'order-second',
        'title' => 'Second',
        'state' => 'draft',
        'position' => 20,
        'date_precision' => 'unknown',
    ]);
    $outside = Artwork::create([
        'artwork_category_id' => $otherGallery->id,
        'slug' => 'order-outside',
        'title' => 'Outside',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);

    Livewire::test(ManageGalleryArtworks::class, ['gallery' => $gallery->id])
        ->call('moveArtwork', $second->id, 'up')
        ->assertHasNoErrors();

    expect((int) $second->fresh()->position)->toBe(0)
        ->and((int) $first->fresh()->position)->toBe(1)
        ->and((int) $outside->fresh()->position)->toBe(10)
        ->and(AuditEvent::query()
            ->where('action', 'artwork_category.gallery_reordered')
            ->where('entity_type', 'artwork_category')
            ->where('entity_id', $gallery->id)
            ->exists())->toBeTrue();
});
