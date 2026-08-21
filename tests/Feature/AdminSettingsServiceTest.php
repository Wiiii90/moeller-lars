<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Blog\BlogEditorialService;
use App\Models\AuditEvent;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('audits website and blog settings changes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $service = app(AdminSettingsService::class);
    $website = PublicContentSetting::query()->sole();
    $blog = BlogSetting::query()->sole();

    $service->updatePublicContent($website, ['public_email' => 'artist@example.test']);
    $service->updateBlog($blog, ['listing_title' => 'News']);

    expect(AuditEvent::query()->where('action', 'public_content_setting.updated')->where('entity_type', 'public_content_setting')->count())
        ->toBe(1)
        ->and(AuditEvent::query()->where('action', 'blog_setting.updated')->where('entity_type', 'blog_setting')->count())
        ->toBe(1);

    $service->updatePublicContent($website->fresh(), ['public_email' => 'artist@example.test']);
    $service->updateBlog($blog->fresh(), ['listing_title' => 'News']);

    expect(AuditEvent::query()->count())->toBe(2);
});

it('requires an admin actor for settings changes', function () {
    $website = PublicContentSetting::query()->sole();

    expect(fn () => app(AdminSettingsService::class)->updatePublicContent($website, ['public_email' => 'artist@example.test']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => app(AdminSettingsService::class)->updatePublicContent($website, ['public_email' => 'artist@example.test']))
        ->toThrow(AuthorizationException::class);
});

it('accepts the existing audited blog editorial boundary', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $post = app(BlogEditorialService::class)->create([
        'title' => 'Admin audit check',
        'slug' => 'admin-audit-check',
        'body' => null,
        'state' => 'draft',
        'position' => 0,
        'excerpt' => null,
        'cover_media_asset_id' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);

    expect(AuditEvent::query()
        ->where('action', 'blog_post.created')
        ->where('entity_type', 'blog_post')
        ->where('entity_id', $post->getKey())
        ->where('admin_user_id', $admin->getKey())
        ->exists())->toBeTrue();
});
