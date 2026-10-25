<?php

use App\Domain\Storage\SiteStorageReclaimService;
use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Admin\AdminAuditService;
use App\Livewire\Admin\SiteStorageReclaimControl;
use App\Models\AdminNotification;
use App\Models\MediaAsset;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

    $existingLatest = PublicationCheckpoint::query()
        ->orderByDesc('published_at')
        ->orderByDesc('id')
        ->firstOrFail();
    $basePublishedAt = $existingLatest->getAttribute('published_at')->copy();

    $releasable = reclaimCheckpoint($actor, 'releasable', $basePublishedAt->copy()->addSecond());
    $workingSource = reclaimCheckpoint($actor, 'working-source', $basePublishedAt->copy()->addSeconds(2));
    $live = reclaimCheckpoint($actor, 'live', $basePublishedAt->copy()->addSeconds(3));

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

    foreach ([$workingSource, $live] as $checkpoint) {
        $assetId = 100000 + $checkpoint->getKey();
        $originalKey = 'originals/retained-'.$assetId.'.jpg';
        $variantKey = 'variants/retained-'.$assetId.'.webp';
        Storage::disk(config('media.disk'))->put($originalKey, 'retained-original');
        Storage::disk(config('media.disk'))->put($variantKey, 'retained-variant');
        foreach ([$checkpoint, $releasable] as $snapshot) {
            foreach ([
                ['media_assets', $assetId, ['id' => $assetId, 'storage_key' => $originalKey, 'state' => 'available']],
                ['media_variants', $assetId, ['id' => $assetId, 'media_asset_id' => $assetId, 'storage_key' => $variantKey, 'state' => 'available']],
            ] as [$table, $rowKey, $payload]) {
                DB::statement(
                    'INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) VALUES (?, ?, ?, ?::jsonb)',
                    [$snapshot->getKey(), $table, (string) $rowKey, json_encode($payload, JSON_THROW_ON_ERROR)],
                );
            }
        }
    }

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
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $workingSource->getKey())->count())->toBe(3)
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $live->getKey())->count())->toBe(3)
        ->and(Storage::disk(config('media.disk'))->exists('originals/reclaim-old.jpg'))->toBeFalse();
    expect(app(SiteStorageReclaimService::class)->reclaim()['changed'])->toBeFalse();
    foreach ([$workingSource, $live] as $checkpoint) {
        $assetId = 100000 + $checkpoint->getKey();
        Storage::disk(config('media.disk'))->assertExists('originals/retained-'.$assetId.'.jpg');
        Storage::disk(config('media.disk'))->assertExists('variants/retained-'.$assetId.'.webp');
    }
});

it('reports one real reclaim and stays silent when repeated without changes', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    reclaimCheckpoint($actor, 'new-live', \Carbon\Carbon::parse(PublicationCheckpoint::query()->max('published_at'))->addSecond());
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(SiteStorageReclaimControl::class)
        ->mountAction('freeStorage')->callMountedAction()
        ->assertDispatched('storage-reclaimed')
        ->assertDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);

    $component->mountAction('freeStorage')->callMountedAction()
        ->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('retains failed physical cleanup for retry without reporting a successful no-op', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    app(SiteStorageReclaimService::class)->reclaim();
    app(AdminActivityNotificationContext::class)->discard();
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/retry.jpg', 'original_filename' => 'retry.jpg',
        'mime_type' => 'image/jpeg', 'byte_size' => 4,
        'sha256' => hash('sha256', 'data'), 'state' => 'deleted',
    ]);
    $disk = Storage::disk(config('media.disk'));
    $disk->put($asset->storage_key, 'data');
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('delete')->with($asset->storage_key)->once()->andReturnFalse();
    Storage::shouldReceive('disk')->andReturn($failingDisk);
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(SiteStorageReclaimControl::class)
        ->mountAction('freeStorage')->callMountedAction()
        ->assertNotDispatched('admin-header-notification');
    expect($disk->exists($asset->storage_key))->toBeTrue()
        ->and(hash_file('sha256', $disk->path($asset->storage_key)))->toBe($asset->sha256)
        ->and(DB::table('publication_media_cleanups')->where('media_asset_id', $asset->id)->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);

    $failingDisk->shouldReceive('delete')->with($asset->storage_key)->once()->andReturnUsing(fn () => $disk->delete($asset->storage_key));
    Livewire::test(SiteStorageReclaimControl::class)
        ->mountAction('freeStorage')->callMountedAction()
        ->assertDispatched('admin-header-notification');
    expect($disk->exists($asset->storage_key))->toBeFalse()
        ->and(DB::table('publication_media_cleanups')->where('media_asset_id', $asset->id)->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('rejects reclaim after administrative authorization is lost', function (bool $guest): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $component = Livewire::test(SiteStorageReclaimControl::class)->mountAction('freeStorage');
    if ($guest) {
        Auth::guard('web')->logout();
    } else {
        $actor->forceFill(['is_admin' => false])->save();
    }
    $snapshotsBefore = PublicationCheckpoint::query()->where('snapshot_available', true)->count();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $component->callMountedAction()->assertForbidden();
    expect(PublicationCheckpoint::query()->where('snapshot_available', true)->count())->toBe($snapshotsBefore)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
})->with(['guest' => true, 'revoked admin' => false]);

it('rolls back recovery metadata when the required audit cannot be persisted', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $previous = PublicationCheckpoint::query()->orderByDesc('published_at')->firstOrFail();
    reclaimCheckpoint($actor, 'rollback-live', $previous->published_at->copy()->addSecond());
    $bytes = 'Original required by the recoverable snapshot';
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/audit-rollback.jpg', 'original_filename' => 'audit-rollback.jpg',
        'mime_type' => 'image/jpeg', 'byte_size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes), 'state' => 'deleted',
    ]);
    $disk = Storage::disk(config('media.disk'));
    $disk->put($asset->storage_key, $bytes);
    DB::table('publication_version_rows')->insert([
        'publication_checkpoint_id' => $previous->id, 'table_name' => 'media_assets',
        'row_key' => (string) $asset->id,
        'payload' => json_encode([...$asset->getAttributes(), 'state' => 'available'], JSON_THROW_ON_ERROR),
    ]);
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $audit = Mockery::mock(app(AdminAuditService::class));
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(AdminAuditService::class, $audit);

    expect(fn () => app(SiteStorageReclaimService::class)->reclaim())->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($previous->fresh()->snapshot_available)->toBeTrue()
        ->and($disk->exists($asset->storage_key))->toBeTrue()
        ->and(hash_file('sha256', $disk->path($asset->storage_key)))->toBe($asset->sha256)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
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
