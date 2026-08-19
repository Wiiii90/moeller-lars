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

it('reorders top-level sections and keeps transitional legacy positions aligned', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    /** @var SiteSection $blog */
    $blog = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();
    /** @var SiteSection $previous */
    $previous = SiteSection::query()
        ->whereNull('parent_id')
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
