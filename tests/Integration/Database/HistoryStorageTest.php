<?php

use App\Domain\Publication\PublicationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('deduplicates unchanged publication payloads while retaining complete commit manifests', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);
    $settingId = (int) DB::table('public_content_settings')
        ->where('scope', 'general')
        ->value('id');

    DB::table('public_content_settings')
        ->where('id', $settingId)
        ->update(['legal_disclaimer' => 'History storage version A']);

    $first = $publication->commit($actor, 'History storage A');
    $firstManifestCount = DB::table('publication_version_rows')
        ->where('publication_checkpoint_id', $first->getKey())
        ->count();
    $firstPayloadCount = DB::table('history_payloads')->count();

    $unchanged = DB::table('publication_version_row_manifests')
        ->where('publication_checkpoint_id', $first->getKey())
        ->whereRaw('NOT (table_name = ? AND row_key = ?)', ['public_content_settings', (string) $settingId])
        ->orderBy('id')
        ->first(['table_name', 'row_key', 'payload_id']);

    expect($firstManifestCount)->toBeGreaterThan(1)
        ->and($firstPayloadCount)->toBeGreaterThan(0)
        ->and($unchanged)->not->toBeNull();

    $firstChangedPayloadId = (int) DB::table('publication_version_row_manifests')
        ->where('publication_checkpoint_id', $first->getKey())
        ->where('table_name', 'public_content_settings')
        ->where('row_key', (string) $settingId)
        ->value('payload_id');

    DB::table('public_content_settings')
        ->where('id', $settingId)
        ->update(['legal_disclaimer' => 'History storage version B']);

    $second = $publication->commit($actor, 'History storage B');
    $secondManifestCount = DB::table('publication_version_rows')
        ->where('publication_checkpoint_id', $second->getKey())
        ->count();
    $secondPayloadCount = DB::table('history_payloads')->count();

    $secondUnchangedPayloadId = (int) DB::table('publication_version_row_manifests')
        ->where('publication_checkpoint_id', $second->getKey())
        ->where('table_name', (string) $unchanged->table_name)
        ->where('row_key', (string) $unchanged->row_key)
        ->value('payload_id');
    $secondChangedPayloadId = (int) DB::table('publication_version_row_manifests')
        ->where('publication_checkpoint_id', $second->getKey())
        ->where('table_name', 'public_content_settings')
        ->where('row_key', (string) $settingId)
        ->value('payload_id');

    expect($secondManifestCount)->toBe($firstManifestCount)
        ->and($secondUnchangedPayloadId)->toBe((int) $unchanged->payload_id)
        ->and($secondChangedPayloadId)->not->toBe($firstChangedPayloadId)
        ->and($secondPayloadCount - $firstPayloadCount)->toBeLessThan($secondManifestCount);
});

it('globally removes expired undo receipts without deleting their permanent activity events', function (): void {
    $firstAdmin = User::factory()->admin()->create();
    $secondAdmin = User::factory()->admin()->create();

    $expiredAuditId = (int) DB::table('audit_events')->insertGetId([
        'admin_user_id' => $firstAdmin->getKey(),
        'action' => 'artwork.updated',
        'entity_type' => 'artwork',
        'entity_id' => 101,
        'occurred_at' => now()->subYear(),
        'request_id' => 'expired-history-storage-test',
        'metadata' => null,
    ]);

    DB::table('admin_action_receipts')->insert([
        'audit_event_id' => $expiredAuditId,
        'admin_user_id' => $firstAdmin->getKey(),
        'action_key' => 'artwork.updated',
        'inverse_action_key' => 'artwork.updated',
        'entity_type' => 'artwork',
        'entity_id' => 101,
        'before_state' => 'before',
        'after_state' => 'after',
        'receipt_version' => 1,
        'expires_at' => now()->subMinute(),
        'undone_at' => null,
        'created_at' => now()->subYear(),
    ]);

    expect(DB::table('admin_action_receipts')->where('audit_event_id', $expiredAuditId)->exists())->toBeTrue();

    $freshAuditId = (int) DB::table('audit_events')->insertGetId([
        'admin_user_id' => $secondAdmin->getKey(),
        'action' => 'artwork.updated',
        'entity_type' => 'artwork',
        'entity_id' => 202,
        'occurred_at' => now(),
        'request_id' => 'fresh-history-storage-test',
        'metadata' => null,
    ]);

    DB::table('admin_action_receipts')->insert([
        'audit_event_id' => $freshAuditId,
        'admin_user_id' => $secondAdmin->getKey(),
        'action_key' => 'artwork.updated',
        'inverse_action_key' => 'artwork.updated',
        'entity_type' => 'artwork',
        'entity_id' => 202,
        'before_state' => 'before',
        'after_state' => 'after',
        'receipt_version' => 1,
        'expires_at' => now()->addDay(),
        'undone_at' => null,
        'created_at' => now(),
    ]);

    expect(DB::table('admin_action_receipts')->where('audit_event_id', $expiredAuditId)->exists())->toBeFalse()
        ->and(DB::table('admin_action_receipts')->where('audit_event_id', $freshAuditId)->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('id', $expiredAuditId)->exists())->toBeTrue();
});
