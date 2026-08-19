<?php

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Filament\Pages\Activity;
use App\Models\AdminActionStat;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityAdmin(string $email): User
{
    return User::factory()->create([
        'name' => 'Activity Admin',
        'email' => $email,
        'is_admin' => true,
    ]);
}

it('materializes stable action counts per admin as audit events are written', function (): void {
    $first = activityAdmin('activity-first@example.test');
    $second = activityAdmin('activity-second@example.test');
    $audit = app(AdminAuditService::class);

    $audit->record($first, 'site_section.updated', 'site_section', 1);
    $audit->record($first, 'site_section.updated', 'site_section', 1);
    $audit->record($first, 'site_section.reordered', 'site_section', 1);
    $audit->record($second, 'site_section.updated', 'site_section', 1);

    expect(AdminActionStat::query()->where('admin_user_id', $first->getKey())->count())->toBe(2)
        ->and(AdminActionStat::query()
            ->where('admin_user_id', $first->getKey())
            ->where('action_key', 'site_section.updated')
            ->value('use_count'))->toBe(2)
        ->and(AdminActionStat::query()
            ->where('admin_user_id', $second->getKey())
            ->where('action_key', 'site_section.updated')
            ->value('use_count'))->toBe(1);
});

it('projects paginated activity into current editorial context and filters by area', function (): void {
    $admin = activityAdmin('activity-feed@example.test');
    $category = ArtworkCategory::create([
        'name' => 'Activity Gallery',
        'slug' => 'activity-gallery',
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'activity-work',
        'title' => 'Activity Work',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $audit = app(AdminAuditService::class);
    $audit->record($admin, 'artwork.updated', 'artwork', (int) $artwork->getKey());
    $audit->record($admin, 'public_content_setting.updated', 'public_content_setting', 1);

    $feed = app(AdminActivityFeed::class)->page('Artwork');

    expect($feed['activity'])->toHaveCount(1)
        ->and($feed['activity'][0]['action'])->toBe('Edited artwork')
        ->and($feed['activity'][0]['area'])->toBe('Artwork')
        ->and($feed['activity'][0]['target'])->toBe('Activity Work')
        ->and($feed['activity'][0]['actor'])->toBe('Activity Admin')
        ->and($feed['activity'][0]['url'])->toContain('/admin/artworks/'.$artwork->getKey().'/edit')
        ->and($feed['paginator']->total())->toBe(1);
});

it('keeps deleted targets readable without exposing raw ids as the primary label', function (): void {
    $admin = activityAdmin('activity-deleted@example.test');
    app(AdminAuditService::class)->record($admin, 'media.deleted', 'media_asset', 987654);

    $feed = app(AdminActivityFeed::class)->recent();

    expect($feed)->toHaveCount(1)
        ->and($feed[0]['action'])->toBe('Deleted media')
        ->and($feed[0]['target'])->toBe('Media no longer available')
        ->and($feed[0]['url'])->toBeNull();
});

it('serves the Activity manager to admins with editorial filters', function (): void {
    $admin = activityAdmin('activity-page@example.test');

    $this->actingAs($admin)
        ->get(Activity::getUrl())
        ->assertSuccessful()
        ->assertSee('Editorial history')
        ->assertSee('All areas')
        ->assertSee('All changes');
});
