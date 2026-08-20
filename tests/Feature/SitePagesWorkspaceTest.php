<?php

use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Pages\SitePages;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\BlogSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('serves the typed Pages workspace to an admin', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'web')
        ->get(SitePages::getUrl())
        ->assertSuccessful()
        ->assertSee('Public pages')
        ->assertSee('Site structure')
        ->assertSee('Vita')
        ->assertSee('Blog')
        ->assertSee('Exhibitions');
});

it('keeps Home pinned outside normal navigation reordering', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $order = app(SiteSectionOrderService::class);

    expect($order->canMove($home, 'up'))->toBeFalse()
        ->and($order->canMove($home, 'down'))->toBeFalse()
        ->and($order->move($home, 'down'))->toBeFalse();
});

it('reorders top-level sections without mirroring into legacy settings', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $blog = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();
    $previous = SiteSection::query()
        ->whereNull('parent_id')
        ->where('type', '<>', SiteSection::TYPE_HOME)
        ->where('position', '<', $blog->position)
        ->orderByDesc('position')
        ->firstOrFail();

    $blogPosition = (int) $blog->position;
    $previousPosition = (int) $previous->position;
    $legacyBlogPosition = (int) BlogSetting::query()->findOrFail(1)->getRawOriginal('navigation_position');

    expect(app(SiteSectionOrderService::class)->move($blog, 'up'))->toBeTrue()
        ->and((int) $blog->fresh()->position)->toBe($previousPosition)
        ->and((int) $previous->fresh()->position)->toBe($blogPosition)
        ->and((int) BlogSetting::query()->findOrFail(1)->getRawOriginal('navigation_position'))->toBe($legacyBlogPosition)
        ->and(AuditEvent::query()->where('action', 'site_section.reordered')->where('entity_id', $blog->id)->exists())->toBeTrue();
});

it('reorders Gallery children only inside their canonical submenu', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create(['name' => 'Parent Gallery', 'slug' => 'parent-gallery', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, ['state' => 'hidden', 'position' => 200]);
    $first = ArtworkCategory::create(['name' => 'First Child', 'slug' => 'first-child', 'show_on_home' => false]);
    $firstSection = testGallerySection($first, ['state' => 'hidden', 'parent_id' => $parentSection->id, 'position' => 10]);
    $second = ArtworkCategory::create(['name' => 'Second Child', 'slug' => 'second-child', 'show_on_home' => false]);
    $secondSection = testGallerySection($second, ['state' => 'hidden', 'parent_id' => $parentSection->id, 'position' => 20]);

    expect(app(SiteSectionOrderService::class)->move($secondSection, 'up'))->toBeTrue()
        ->and((int) $secondSection->fresh()->position)->toBe(10)
        ->and((int) $firstSection->fresh()->position)->toBe(20)
        ->and((int) $second->fresh()->getRawOriginal('position'))->toBe(0)
        ->and((int) $first->fresh()->getRawOriginal('position'))->toBe(0)
        ->and((int) $parent->fresh()->getRawOriginal('position'))->toBe(0);
});

it('edits Gallery publication and hierarchy from Pages without writing legacy category placement', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create(['name' => 'Visible Parent', 'slug' => 'visible-parent', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, [
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);
    $gallery = ArtworkCategory::create(['name' => 'Movable Gallery', 'slug' => 'movable-gallery', 'show_on_home' => false]);
    $section = testGallerySection($gallery, ['state' => 'hidden', 'position' => 210]);
    $legacyGalleryState = $gallery->fresh()->getRawOriginal('state');

    Livewire::test(SitePages::class)
        ->call('moveGallery', $section->id, $parentSection->id)
        ->call('toggleGalleryState', $section->id)
        ->call('toggleGalleryNavigation', $section->id)
        ->assertHasNoErrors();

    $freshSection = $section->fresh();
    $freshCategory = $gallery->fresh();

    expect((int) $freshSection->parent_id)->toBe($parentSection->id)
        ->and($freshSection->state)->toBe('published')
        ->and((bool) $freshSection->show_in_navigation)->toBeTrue()
        ->and($freshCategory->getRawOriginal('parent_id'))->toBeNull()
        ->and($freshCategory->getRawOriginal('state'))->toBe($legacyGalleryState)
        ->and((bool) $freshCategory->getRawOriginal('show_in_navigation'))->toBeTrue()
        ->and((int) $freshCategory->getRawOriginal('position'))->toBe(0)
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->where('entity_id', $section->id)->count())->toBe(3);
});

it('does not hide a navigation parent while it still has a visible submenu Gallery', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create(['name' => 'Required Parent', 'slug' => 'required-parent', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, [
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);
    $child = ArtworkCategory::create(['name' => 'Visible Child', 'slug' => 'visible-child', 'show_on_home' => false]);
    testGallerySection($child, [
        'state' => 'published',
        'show_in_navigation' => true,
        'parent_id' => $parentSection->id,
        'position' => 10,
    ]);
    $legacyParentState = $parent->fresh()->getRawOriginal('state');

    Livewire::test(SitePages::class)
        ->call('toggleGalleryState', $parentSection->id)
        ->assertHasNoErrors();

    expect($parentSection->fresh()->state)->toBe('published')
        ->and($parent->fresh()->getRawOriginal('state'))->toBe($legacyParentState);
});
