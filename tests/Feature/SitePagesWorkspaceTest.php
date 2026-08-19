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

    /** @var SiteSection $home */
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $order = app(SiteSectionOrderService::class);

    expect($order->canMove($home, 'up'))->toBeFalse()
        ->and($order->canMove($home, 'down'))->toBeFalse()
        ->and($order->move($home, 'down'))->toBeFalse();
});

it('reorders top-level sections and keeps transitional legacy positions aligned', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    /** @var SiteSection $blog */
    $blog = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();
    /** @var SiteSection $previous */
    $previous = SiteSection::query()
        ->whereNull('parent_id')
        ->where('type', '<>', SiteSection::TYPE_HOME)
        ->where('position', '<', $blog->position)
        ->orderByDesc('position')
        ->firstOrFail();

    $blogPosition = (int) $blog->position;
    $previousPosition = (int) $previous->position;

    expect(app(SiteSectionOrderService::class)->move($blog, 'up'))->toBeTrue()
        ->and((int) $blog->fresh()->position)->toBe($previousPosition)
        ->and((int) $previous->fresh()->position)->toBe($blogPosition)
        ->and((int) BlogSetting::query()->findOrFail(1)->navigation_position)->toBe($previousPosition)
        ->and(AuditEvent::query()->where('action', 'site_section.reordered')->where('entity_id', $blog->id)->exists())->toBeTrue();
});

it('reorders gallery children only inside their submenu and mirrors category positions', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create([
        'name' => 'Parent Gallery',
        'slug' => 'parent-gallery',
        'state' => 'published',
        'position' => 200,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $first = ArtworkCategory::create([
        'name' => 'First Child',
        'slug' => 'first-child',
        'state' => 'published',
        'position' => 10,
        'parent_id' => $parent->id,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $second = ArtworkCategory::create([
        'name' => 'Second Child',
        'slug' => 'second-child',
        'state' => 'published',
        'position' => 20,
        'parent_id' => $parent->id,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);

    /** @var SiteSection $secondSection */
    $secondSection = SiteSection::query()->where('artwork_category_id', $second->id)->firstOrFail();

    expect(app(SiteSectionOrderService::class)->move($secondSection, 'up'))->toBeTrue()
        ->and((int) $secondSection->fresh()->position)->toBe(10)
        ->and((int) SiteSection::query()->where('artwork_category_id', $first->id)->firstOrFail()->position)->toBe(20)
        ->and((int) $second->fresh()->position)->toBe(10)
        ->and((int) $first->fresh()->position)->toBe(20)
        ->and((int) $parent->fresh()->position)->toBe(200);
});

it('edits Gallery publication and hierarchy from Pages while keeping legacy category fields aligned', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create([
        'name' => 'Visible Parent',
        'slug' => 'visible-parent',
        'state' => 'published',
        'position' => 200,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    $gallery = ArtworkCategory::create([
        'name' => 'Movable Gallery',
        'slug' => 'movable-gallery',
        'state' => 'hidden',
        'position' => 210,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);

    /** @var SiteSection $parentSection */
    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->id)->firstOrFail();
    /** @var SiteSection $section */
    $section = SiteSection::query()->where('artwork_category_id', $gallery->id)->firstOrFail();

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
        ->and((int) $freshCategory->parent_id)->toBe($parent->id)
        ->and($freshCategory->state)->toBe('published')
        ->and((bool) $freshCategory->show_in_navigation)->toBeTrue()
        ->and((int) $freshCategory->position)->toBe((int) $freshSection->position)
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->where('entity_id', $section->id)->count())->toBe(3);
});

it('does not hide a navigation parent while it still has a visible submenu Gallery', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $parent = ArtworkCategory::create([
        'name' => 'Required Parent',
        'slug' => 'required-parent',
        'state' => 'published',
        'position' => 220,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    ArtworkCategory::create([
        'name' => 'Visible Child',
        'slug' => 'visible-child',
        'state' => 'published',
        'position' => 10,
        'parent_id' => $parent->id,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    /** @var SiteSection $parentSection */
    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->id)->firstOrFail();

    Livewire::test(SitePages::class)
        ->call('toggleGalleryState', $parentSection->id)
        ->assertHasNoErrors();

    expect($parentSection->fresh()->state)->toBe('published')
        ->and($parent->fresh()->state)->toBe('published');
});
