<?php

use App\Domain\Admin\AdminUndoService;
use App\Domain\Content\BlogEditorialService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('roundtrips a media-only Blog update as one atomic snapshot Undo', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $sectionId = (int) DB::table('site_sections')
        ->where('type', 'journal')
        ->where('template', 'blog')
        ->value('id');
    expect($sectionId)->toBeGreaterThan(0);

    $originalAssetId = (int) DB::table('media_assets')->insertGetId([
        'storage_key' => 'media/activity-undo-original.jpg',
        'original_filename' => 'activity-undo-original.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'activity-undo-original'),
        'state' => 'available',
        'alt_text' => 'Original image',
    ]);
    $replacementAssetId = (int) DB::table('media_assets')->insertGetId([
        'storage_key' => 'media/activity-undo-replacement.jpg',
        'original_filename' => 'activity-undo-replacement.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'activity-undo-replacement'),
        'state' => 'available',
        'alt_text' => 'Replacement image',
    ]);
    DB::table('media_variants')->insert([
        'media_asset_id' => $replacementAssetId,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'media/activity-undo-replacement-thumbnail.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'activity-undo-replacement-thumbnail'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    $postId = (int) DB::table('blog_posts')->insertGetId([
        'site_section_id' => $sectionId,
        'slug' => 'activity-media-only-undo',
        'title' => 'Activity media-only Undo',
        'body' => null,
        'state' => 'draft',
        'position' => 0,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);
    $originalUsageId = (int) DB::table('journal_entry_media')->insertGetId([
        'blog_post_id' => $postId,
        'exhibition_id' => null,
        'media_asset_id' => $originalAssetId,
        'role' => 'gallery',
        'position' => 1,
        'alt_text_override' => null,
    ]);

    $eventsBefore = AuditEvent::query()->count();
    $post = BlogPost::query()->findOrFail($postId);

    app(BlogEditorialService::class)->update($post, [
        'site_section_id' => $sectionId,
        'title' => 'Activity media-only Undo',
        'slug' => 'activity-media-only-undo',
        'body' => null,
        'state' => 'draft',
        'position' => 0,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
        'gallery_images' => [
            ['media_asset_id' => $replacementAssetId],
        ],
    ]);

    $event = AuditEvent::query()->where('action', 'blog_post.updated')->latest('id')->firstOrFail();
    $receipt = AdminActionReceipt::query()->where('audit_event_id', $event->getKey())->firstOrFail();
    $eventMetadata = $event->getAttribute('metadata');
    $projectedEvent = app(AdminActivityFeed::class)->event((int) $event->getKey(), $actor);

    expect(AuditEvent::query()->count())->toBe($eventsBefore + 1)
        ->and($event->getAttribute('entity_type'))->toBe('blog_post')
        ->and($eventMetadata['target_label'] ?? null)->toBe('Activity media-only Undo')
        ->and($projectedEvent['target'] ?? null)->toBe('Activity media-only Undo')
        ->and((int) $event->getAttribute('entity_id'))->toBe($postId)
        ->and(DB::table('journal_entry_media')->where('id', $originalUsageId)->exists())->toBeFalse()
        ->and(DB::table('journal_entry_media')->where('blog_post_id', $postId)->where('media_asset_id', $replacementAssetId)->exists())->toBeTrue();

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect(DB::table('journal_entry_media')->where('id', $originalUsageId)->value('media_asset_id'))->toBe($originalAssetId)
        ->and(DB::table('journal_entry_media')->where('id', $originalUsageId)->value('role'))->toBe('gallery')
        ->and((int) DB::table('journal_entry_media')->where('id', $originalUsageId)->value('position'))->toBe(1)
        ->and(DB::table('journal_entry_media')->where('blog_post_id', $postId)->where('media_asset_id', $replacementAssetId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'admin.undo_applied')->where('entity_type', 'blog_post')->where('entity_id', $postId)->exists())->toBeTrue();
});
