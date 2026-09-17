<?php

use App\Domain\Storage\SiteStorageDatabaseUsageService;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('shares history payloads across Undo and Publication roots and collects them after the final reference is released', function (): void {
    $actor = User::factory()->admin()->create();

    $payload = [
        'rows' => [[
            'entity_type' => 'public_content_setting',
            'table' => 'public_content_settings',
            'row_id' => 99101,
            'before' => ['id' => 99101, 'public_email' => 'before@example.test'],
            'after' => ['id' => 99101, 'public_email' => 'after@example.test'],
        ]],
    ];

    $first = sharedHistoryReceipt($actor, 1, $payload);
    $second = sharedHistoryReceipt($actor, 2, $payload);
    $first = AdminActionReceipt::query()->findOrFail($first->getKey());
    $second = AdminActionReceipt::query()->findOrFail($second->getKey());

    $payloadId = (int) $first->getAttribute('snapshot_payload_id');

    expect($payloadId)->toBeGreaterThan(0)
        ->and((int) $second->getAttribute('snapshot_payload_id'))->toBe($payloadId)
        ->and($first->getAttribute('snapshot_payload'))->toEqual($payload)
        ->and($second->getAttribute('snapshot_payload'))->toEqual($payload)
        ->and(DB::table('history_payloads')->where('id', $payloadId)->count())->toBe(1);

    $checkpoint = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Shared history payload test',
        'change_count' => 1,
        'published_at' => now(),
        'hash' => hash('sha256', 'shared-history-checkpoint'),
        'snapshot_hash' => hash('sha256', 'shared-history-snapshot'),
        'schema_hash' => hash('sha256', 'shared-history-schema'),
        'snapshot_available' => true,
        'operation' => 'commit',
        'parent_publication_checkpoint_id' => null,
        'source_publication_checkpoint_id' => null,
    ]);

    DB::statement(
        'INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) VALUES (?, ?, ?, ?::jsonb)',
        [
            $checkpoint->getKey(),
            'public_content_settings',
            'shared-history-payload-test',
            json_encode($payload, JSON_THROW_ON_ERROR),
        ],
    );

    $publicationPayloadId = (int) DB::table('publication_version_row_manifests')
        ->where('publication_checkpoint_id', $checkpoint->getKey())
        ->where('row_key', 'shared-history-payload-test')
        ->value('payload_id');
    $storage = app(SiteStorageDatabaseUsageService::class)->snapshot();

    expect($publicationPayloadId)->toBe($payloadId)
        ->and($storage['shared_history_bytes'])->toBeGreaterThan(0)
        ->and($storage['undo_logical_bytes'])->toBeGreaterThanOrEqual(
            (int) $first->getAttribute('logical_bytes') + (int) $second->getAttribute('logical_bytes'),
        );

    $first->delete();
    expect(DB::table('history_payloads')->where('id', $payloadId)->exists())->toBeTrue();

    $second->delete();
    expect(DB::table('history_payloads')->where('id', $payloadId)->exists())->toBeTrue();

    DB::table('publication_version_rows')
        ->where('publication_checkpoint_id', $checkpoint->getKey())
        ->where('row_key', 'shared-history-payload-test')
        ->delete();

    expect(DB::table('history_payloads')->where('id', $payloadId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('admin_user_id', $actor->getKey())->count())->toBe(2);
});

/** @param array<string, mixed> $payload */
function sharedHistoryReceipt(User $actor, int $sequence, array $payload): AdminActionReceipt
{
    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting',
        'entity_id' => 99000 + $sequence,
        'occurred_at' => now()->addSeconds($sequence),
        'request_id' => null,
        'metadata' => null,
    ]);

    return AdminActionReceipt::query()->create([
        'audit_event_id' => $event->getKey(),
        'admin_user_id' => $actor->getKey(),
        'action_key' => 'public_content_setting.updated',
        'inverse_action_key' => 'admin.undo_applied',
        'entity_type' => 'public_content_setting',
        'entity_id' => 99000 + $sequence,
        'before_state' => 'snapshot',
        'after_state' => 'snapshot',
        'snapshot_payload' => $payload,
        'receipt_version' => 1,
        'expires_at' => now()->addDays(365),
        'created_at' => now()->addSeconds($sequence),
    ]);
}
