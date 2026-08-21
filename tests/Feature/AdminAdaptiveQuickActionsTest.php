<?php

use App\Domain\Admin\AdminQuickActionService;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Widgets\ArtistDashboard;
use App\Models\AdminActionStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adaptiveQuickActionAdmin(string $email): User
{
    return User::factory()->create([
        'name' => 'Adaptive Admin',
        'email' => $email,
        'is_admin' => true,
    ]);
}

it('ranks a bounded per-user shortcut set from compact frequency and recency stats', function (): void {
    $admin = adaptiveQuickActionAdmin('adaptive-primary@example.test');
    $other = adaptiveQuickActionAdmin('adaptive-other@example.test');

    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'blog_post.updated',
        'use_count' => 8,
        'last_used_at' => now()->subDays(2),
    ]);
    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'exhibition.updated',
        'use_count' => 4,
        'last_used_at' => now()->subHours(3),
    ]);
    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'cv_entry.updated',
        'use_count' => 2,
        'last_used_at' => now()->subHour(),
    ]);
    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'public_content_setting.updated',
        'use_count' => 2,
        'last_used_at' => now()->subDays(40),
    ]);
    AdminActionStat::create([
        'admin_user_id' => $other->getKey(),
        'action_key' => 'media.ingested',
        'use_count' => 64,
        'last_used_at' => now(),
    ]);

    $actions = app(AdminQuickActionService::class)->forUser($admin);

    expect($actions)->toHaveCount(3)
        ->and(array_column($actions, 'key'))->toBe(['blog-create', 'exhibition-create', 'cv-create'])
        ->and($actions[0]['url'])->toBe(BlogPostResource::getUrl('create'))
        ->and($actions[0]['reason'])->toContain('8 related actions')
        ->and(array_column($actions, 'key'))->not->toContain('media');
});

it('requires repeated usage before personalizing the dashboard', function (): void {
    $admin = adaptiveQuickActionAdmin('adaptive-threshold@example.test');

    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'blog_post.created',
        'use_count' => 1,
        'last_used_at' => now(),
    ]);

    expect(app(AdminQuickActionService::class)->forUser($admin))->toBe([]);

    AdminActionStat::query()
        ->where('admin_user_id', $admin->getKey())
        ->where('action_key', 'blog_post.created')
        ->update(['use_count' => 2]);

    expect(app(AdminQuickActionService::class)->forUser($admin))
        ->toHaveCount(1)
        ->and(app(AdminQuickActionService::class)->forUser($admin)[0]['key'])->toBe('blog-create');
});

it('aggregates related stable action keys into one explainable destination', function (): void {
    $admin = adaptiveQuickActionAdmin('adaptive-aggregate@example.test');

    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'artwork.updated',
        'use_count' => 3,
        'last_used_at' => now()->subDays(3),
    ]);
    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'artwork_category.updated',
        'use_count' => 5,
        'last_used_at' => now()->subDay(),
    ]);

    $actions = app(AdminQuickActionService::class)->forUser($admin);

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['key'])->toBe('pages')
        ->and($actions[0]['reason'])->toContain('8 related actions');
});

it('renders adaptive shortcuts inside the dashboard quick actions', function (): void {
    $admin = adaptiveQuickActionAdmin('adaptive-widget@example.test');
    AdminActionStat::create([
        'admin_user_id' => $admin->getKey(),
        'action_key' => 'blog_post.updated',
        'use_count' => 4,
        'last_used_at' => now(),
    ]);

    $this->actingAs($admin);

    Livewire::test(ArtistDashboard::class)
        ->assertSee('Quick actions')
        ->assertSee('New blog post')
        ->assertSee('Based on repeated admin work');
});
