<?php

use App\Domain\Storage\SiteStorageReclaimService;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('site-storage-reclaim-test');
    config([
        'media.disk' => 'site-storage-reclaim-test',
        'media.quota_bytes' => 10_000_000_000,
    ]);
});

it('frees recovery roots while preserving Activity and protected publication snapshots', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $originalEvent = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting',
        'entity_id' => 1,
        'occurred_at' => now()->subMinute(),
        'request_id' => null,
        'metadata' => null,
    ]);

    AdminActionReceipt::query()->create([
        'audit_event_id' => $originalEvent->getKey(),
        'admin_user_id' => $actor->getKey(),
        'action_key' => 'public_content_setting.updated',
        'inverse_action_key' => 'admin.undo_applied',
        'entity_type' => 'public_content_setting',
        'entity_id' => 1,
        'before_state' => 'before',
        'after_state' => 'after',
        'snapshot_payload' => ['rows' => []],
        'receipt_version' => 1,
        'expires_at' => now()->addDays(365),
        'created_at' => now()->subMinute(),
    ]);

    $releasable = reclaimCheckpoint($actor, 'releasable', now()->subSeconds(30));
    $workingSource = reclaimCheckpoint($actor, 'working-source', now()->subSeconds(20));
    $live = reclaimCheckpoint($actor, 'live', now()->subSeconds(10));

    DB::table('publication_working_context')->where('id', 1)->update([
        'operation' => 'restore',
        'source_publication_checkpoint_id' => $workingSource->getKey(),
        'updated_at' => now(),
    ]);

    Storage::disk(config('media.disk'))->put('originals/reclaim-old.jpg', 'old-recovery-media');

    DB::statement(
        'INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) VALUES (?, ?, ?, ?::jsonb)',
        [
            $releasable->getKey(),
            'media_assets',
            '99001',
            json_encode([
                'id' => 99001,
                'storage_key' => 'originals/reclaim-old.jpg',
                'state' => 'available',
            ], JSON_THROW_ON_ERROR),
        ],
    );
    DB::statement(
        'INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) VALUES (?, ?, ?, ?::jsonb)',
        [
            $workingSource->getKey(),
            'public_content_settings',
            '99002',
            json_encode(['id' => 99002], JSON_THROW_ON_ERROR),
        ],
    );
    DB::statement(
        'INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) VALUES (?, ?, ?, ?::jsonb)',
        [
            $live->getKey(),
            'public_content_settings',
            '99003',
            json_encode(['id' => 99003], JSON_THROW_ON_ERROR),
        ],
    );

    $result = app(SiteStorageReclaimService::class)->reclaim();

    expect($result['undo_receipts'])->toBeGreaterThanOrEqual(1)
        ->and($result['publication_snapshots'])->toBeGreaterThanOrEqual(1)
        ->and(AdminActionReceipt::query()->count())->toBe(0)
        ->and(AuditEvent::query()->whereKey($originalEvent->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'storage.reclaimed')->count())->toBe(1)
        ->and(PublicationCheckpoint::query()->whereKey($releasable->getKey())->value('snapshot_available'))->toBeFalse()
        ->and(PublicationCheckpoint::query()->whereKey($workingSource->getKey())->value('snapshot_available'))->toBeTrue()
        ->and(PublicationCheckpoint::query()->whereKey($live->getKey())->value('snapshot_available'))->toBeTrue()
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $releasable->getKey())->count())->toBe(0)
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $workingSource->getKey())->count())->toBe(1)
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $live->getKey())->count())->toBe(1)
        ->and(Storage::disk(config('media.disk'))->exists('originals/reclaim-old.jpg'))->toBeFalse();
});

function reclaimCheckpoint(User $actor, string $token, DateTimeInterface $publishedAt): PublicationCheckpoint
{
    return PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => $token,
        'change_count' => 1,
        'published_at' => $publishedAt,
        'hash' => hash('sha256', 'checkpoint-'.$token),
        'snapshot_hash' => hash('sha256', 'snapshot-'.$token),
        'schema_hash' => hash('sha256', 'schema-'.$token),
        'snapshot_available' => true,
        'operation' => 'commit',
        'parent_publication_checkpoint_id' => null,
        'source_publication_checkpoint_id' => null,
    ]);
}
