<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps artist preview non-discoverable to unauthenticated and non-admin users', function (): void {
    $this->get('/preview')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/preview')->assertNotFound();
});

it('marks authenticated preview as noindex and non-cacheable without changing publication state', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $page = app(SiteSectionEditorialService::class)->createCustomPage('Preview page', 'preview-page');
    $response = $this->get('/preview/preview-page');

    $response->assertSuccessful()
        ->assertSee('PREVIEW')
        ->assertSee('name="robots" content="noindex,nofollow,noarchive"', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')->toContain('no-store')->toContain('max-age=0')->toContain('must-revalidate');
    expect($page->fresh()->state)->toBe('hidden');
    $this->get('/preview-page')->assertNotFound();
});

it('shows hidden draft navigation and keeps links inside the preview context', function (): void {
    $admin = User::factory()->admin()->create();
    $group = SiteSection::query()->create([
        'type' => SiteSection::TYPE_NAVIGATION_GROUP, 'title' => 'Works', 'navigation_label' => 'Works', 'slug' => null,
        'state' => 'hidden', 'position' => 500, 'show_in_navigation' => true, 'parent_id' => null, 'artwork_category_id' => null,
    ]);
    $category = ArtworkCategory::create(['name' => 'Studies', 'slug' => 'studies', 'show_on_home' => false]);
    $gallery = testGallerySection($category, ['state' => 'hidden', 'show_in_navigation' => true, 'parent_id' => $group->id, 'position' => 10]);

    $this->get('/studies')->assertNotFound();
    $this->get('/')->assertDontSee('Works');
    $this->actingAs($admin)->get('/preview/studies')
        ->assertSuccessful()->assertSee('Works')->assertSee('Studies')->assertSee('/preview/studies', false)->assertDontSee('href="/works"', false);

    expect($group->fresh()->state)->toBe('hidden')->and($gallery->fresh()->state)->toBe('hidden');
});

it('keeps published public navigation separate from preview-only hidden nodes', function (): void {
    $admin = User::factory()->admin()->create();
    SiteSection::query()->create([
        'type' => SiteSection::TYPE_NAVIGATION_GROUP, 'title' => 'Future', 'navigation_label' => 'Future', 'slug' => null,
        'state' => 'hidden', 'position' => 500, 'show_in_navigation' => true, 'parent_id' => null, 'artwork_category_id' => null,
    ]);

    $this->get('/')->assertSuccessful()->assertDontSee('Future');
    $this->actingAs($admin)->get('/preview')->assertSuccessful()->assertSee('Future');
});
