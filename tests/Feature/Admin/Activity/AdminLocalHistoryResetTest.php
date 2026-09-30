<?php

use App\Domain\Admin\AdminLocalHistoryResetService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Models\AdminActionReceipt;
use App\Models\AdminActivityOrderingProjection;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('clears disposable admin history without deleting editorial or publication content', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $galleries = app(GalleryEditorialService::class);
    $first = $galleries->create([
        'name' => 'Preserved Gallery A',
        'slug' => 'preserved-gallery-a',
        'description' => null,
    ]);
    $second = $galleries->create([
        'name' => 'Preserved Gallery B',
        'slug' => 'preserved-gallery-b',
        'description' => null,
    ]);

    $galleries->update($first, [
        'name' => 'Preserved Gallery A renamed',
        'description' => null,
    ]);

    $secondSection = SiteSection::query()
        ->where('artwork_category_id', $second->getKey())
        ->firstOrFail();
    expect(app(SiteSectionOrderService::class)->move($secondSection, 'up'))->toBeTrue();

    $auditEvent = AuditEvent::query()
        ->where('action', 'artwork_category.updated')
        ->where('entity_id', $first->getKey())
        ->latest('id')
        ->firstOrFail();

    $checkpointId = (int) DB::table('publication_checkpoints')
        ->orderByDesc('id')
        ->value('id');
    DB::table('publication_checkpoint_events')->insert([
        'publication_checkpoint_id' => $checkpointId,
        'audit_event_id' => (int) $auditEvent->getKey(),
        'created_at' => now(),
    ]);

    $notificationId = (int) DB::table('admin_notifications')->insertGetId([
        'user_id' => $actor->getKey(),
        'source_id' => 'local-history-reset-test',
        'type' => 'system',
        'status' => 'info',
        'title' => 'Disposable notification',
        'body' => null,
        'read_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('dashboard_feed_pins')->insert([
        'user_id' => $actor->getKey(),
        'entry_key' => 'notification:'.$notificationId,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('admin_action_stats')->insert([
        'admin_user_id' => $actor->getKey(),
        'action_key' => 'artwork_category.updated',
        'use_count' => 1,
        'last_used_at' => now(),
    ]);

    $checkpointCount = DB::table('publication_checkpoints')->count();
    $manifestCount = DB::table('publication_version_row_manifests')->count();
    $secondPosition = (int) $secondSection->fresh()->getAttribute('position');

    expect(AuditEvent::query()->count())->toBeGreaterThan(0)
        ->and(AdminActionReceipt::query()->count())->toBeGreaterThan(0)
        ->and(AdminActivityOrderingProjection::query()->count())->toBeGreaterThan(0);

    app(AdminLocalHistoryResetService::class)->reset();

    expect($first->fresh()?->getAttribute('name'))->toBe('Preserved Gallery A renamed')
        ->and($second->fresh()?->getAttribute('name'))->toBe('Preserved Gallery B')
        ->and((int) $secondSection->fresh()->getAttribute('position'))->toBe($secondPosition)
        ->and(DB::table('publication_checkpoints')->count())->toBe($checkpointCount)
        ->and(DB::table('publication_version_row_manifests')->count())->toBe($manifestCount)
        ->and(DB::table('audit_events')->count())->toBe(0)
        ->and(DB::table('admin_action_receipts')->count())->toBe(0)
        ->and(DB::table('admin_activity_ordering_events')->count())->toBe(0)
        ->and(DB::table('admin_activity_ordering_projections')->count())->toBe(0)
        ->and(DB::table('admin_notifications')->count())->toBe(0)
        ->and(DB::table('dashboard_feed_pins')->count())->toBe(0)
        ->and(DB::table('admin_action_stats')->count())->toBe(0)
        ->and(DB::table('publication_checkpoint_events')->count())->toBe(0);

    $postResetEventId = DB::table('audit_events')->insertGetId([
        'admin_user_id' => $actor->getKey(),
        'action' => 'local_history_reset.guard_check',
        'entity_type' => 'system',
        'entity_id' => 1,
        'occurred_at' => now(),
        'request_id' => null,
        'metadata' => null,
    ]);

    expect(fn () => DB::table('audit_events')->where('id', $postResetEventId)->delete())
        ->toThrow(QueryException::class);
});
