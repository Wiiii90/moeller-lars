<?php

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Content\BlogEditorialService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('exposes and executes conflict-safe snapshot Undo for updated admin settings', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $settings = PublicContentSetting::general();
    $beforeEmail = $settings->getAttribute('public_email');

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'public_email' => 'undo-snapshot@example.test',
    ]);

    $event = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->latest('id')
        ->firstOrFail();
    $receipt = AdminActionReceipt::query()
        ->where('audit_event_id', $event->getKey())
        ->firstOrFail();

    expect($receipt->getAttribute('snapshot_payload'))->toBeArray()
        ->and($receipt->getAttribute('inverse_action_key'))->toBe('admin.undo_applied');

    $projected = app(AdminActivityFeed::class)->event((int) $event->getKey(), $actor);
    expect($projected)->not->toBeNull()
        ->and($projected['undo']['id'] ?? null)->toBe((int) $receipt->getKey())
        ->and($projected['undo']['inverse_label'] ?? null)->toBe('Restored previous values');

    $result = app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($result['inverse'])->toBe('Restored previous values')
        ->and(DB::table('public_content_settings')->where('scope', 'general')->value('public_email'))->toBe($beforeEmail)
        ->and(AdminActionReceipt::query()->count())->toBe(1)
        ->and($receipt->fresh()?->getAttribute('undone_at'))->not->toBeNull();

    $undoEvent = AuditEvent::query()->where('action', 'admin.undo_applied')->latest('id')->firstOrFail();
    expect($undoEvent->getAttribute('metadata'))->toBe([
        'source_audit_event_id' => (int) $event->getKey(),
    ]);
});

it('hides an older snapshot Undo after the same row changes again', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $service = app(AdminSettingsService::class);
    $settings = PublicContentSetting::general();
    $settings = $service->updatePublicContent($settings, ['public_email' => 'first@example.test']);
    $firstEvent = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    $service->updatePublicContent($settings, ['public_email' => 'second@example.test']);
    $secondEvent = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    $feed = app(AdminActivityFeed::class);
    $first = $feed->event((int) $firstEvent->getKey(), $actor);
    $second = $feed->event((int) $secondEvent->getKey(), $actor);

    expect($first['undo'] ?? null)->toBeNull()
        ->and($second['undo'] ?? null)->not->toBeNull();
});

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
        'embed_key' => null,
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
    $payload = $receipt->getAttribute('snapshot_payload');
    $rows = is_array($payload) && is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    $journalRows = array_values(array_filter(
        $rows,
        static fn (mixed $row): bool => is_array($row) && ($row['table'] ?? null) === 'journal_entry_media',
    ));

    expect(AuditEvent::query()->count())->toBe($eventsBefore + 1)
        ->and($event->getAttribute('entity_type'))->toBe('blog_post')
        ->and((int) $event->getAttribute('entity_id'))->toBe($postId)
        ->and($journalRows)->toHaveCount(2)
        ->and(DB::table('journal_entry_media')->where('id', $originalUsageId)->exists())->toBeFalse()
        ->and(DB::table('journal_entry_media')->where('blog_post_id', $postId)->where('media_asset_id', $replacementAssetId)->exists())->toBeTrue();

    $deletedOriginal = collect($journalRows)->first(
        static fn (array $row): bool => (int) ($row['row_id'] ?? 0) === $originalUsageId,
    );
    $createdReplacement = collect($journalRows)->first(
        static fn (array $row): bool => is_null($row['before'] ?? null) && is_array($row['after'] ?? null),
    );

    expect($deletedOriginal)->toBeArray()
        ->and($deletedOriginal['before']['media_asset_id'] ?? null)->toBe($originalAssetId)
        ->and($deletedOriginal['after'] ?? null)->toBeNull()
        ->and($createdReplacement)->toBeArray()
        ->and($createdReplacement['before'] ?? null)->toBeNull()
        ->and($createdReplacement['after']['media_asset_id'] ?? null)->toBe($replacementAssetId);

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect(DB::table('journal_entry_media')->where('id', $originalUsageId)->value('media_asset_id'))->toBe($originalAssetId)
        ->and(DB::table('journal_entry_media')->where('id', $originalUsageId)->value('role'))->toBe('gallery')
        ->and((int) DB::table('journal_entry_media')->where('id', $originalUsageId)->value('position'))->toBe(1)
        ->and(DB::table('journal_entry_media')->where('blog_post_id', $postId)->where('media_asset_id', $replacementAssetId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'admin.undo_applied')->where('entity_type', 'blog_post')->where('entity_id', $postId)->exists())->toBeTrue();
});

it('does not create snapshot receipts for no-op settings writes and accepts Journal settings audit actions', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $settings = PublicContentSetting::general();
    $currentEmail = $settings->getAttribute('public_email');
    $eventsBefore = AuditEvent::query()->count();
    $receiptsBefore = AdminActionReceipt::query()->count();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'public_email' => $currentEmail,
    ]);

    expect(AuditEvent::query()->count())->toBe($eventsBefore)
        ->and(AdminActionReceipt::query()->count())->toBe($receiptsBefore)
        ->and(AdminActionCatalog::has('journal_setting.updated'))->toBeTrue();

    $event = app(AdminAuditService::class)->record(
        $actor,
        'journal_setting.updated',
        'journal_setting',
        999,
    );

    expect($event->getAttribute('entity_type'))->toBe('journal_setting')
        ->and(AdminActionReceipt::query()->where('audit_event_id', $event->getKey())->exists())->toBeFalse();
});
