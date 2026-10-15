<?php

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Admin\AdminUndoService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        ->and($projected['action_key'])->toBe('public_content_setting.updated')
        ->and($projected['change'])->toBe('Edited website settings')
        ->and($projected['details'])->toContain('Public email')
        ->and($projected['change_summary']['items'][0]['label'] ?? null)->toBe('Public email')
        ->and($projected['change_summary']['items'][0]['after'] ?? null)->toBe('undo-snapshot@example.test')
        ->and($projected['undo']['id'] ?? null)->toBe((int) $receipt->getKey())
        ->and($projected['undo']['inverse_label'] ?? null)->toBeString()
        ->and($projected['undo']['confirmation'] ?? null)->toBeString();

    $result = app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($result['inverse'])->toBeString()
        ->and(DB::table('public_content_settings')->where('scope', 'general')->value('public_email'))->toBe($beforeEmail)
        ->and(AdminActionReceipt::query()->count())->toBe(1)
        ->and($receipt->fresh()?->getAttribute('undone_at'))->not->toBeNull();

    $undoEvent = AuditEvent::query()->where('action', 'admin.undo_applied')->latest('id')->firstOrFail();
    $undoMetadata = $undoEvent->getAttribute('metadata');
    expect($undoMetadata['source_audit_event_id'] ?? null)->toBe((int) $event->getKey())
        ->and($undoMetadata['change_summary']['items'][0]['label'] ?? null)->toBe('Public email')
        ->and($undoMetadata['change_summary']['items'][0]['after'] ?? null)->toBe($beforeEmail === null ? 'None' : (string) $beforeEmail);

    $projectedUndo = app(AdminActivityFeed::class)->event((int) $undoEvent->getKey(), $actor);
    expect($projectedUndo['action_key'] ?? null)->toBe('admin.undo_applied')
        ->and($projectedUndo['change'] ?? null)->toBe('Restored previous values in Activity')
        ->and($projectedUndo['details'] ?? null)->toContain('Public email')
        ->and($projectedUndo['change_summary']['items'][0]['label'] ?? null)->toBe('Public email')
        ->and($projectedUndo['change_summary']['items'][0]['before'] ?? null)->toBe('undo-snapshot@example.test')
        ->and($projectedUndo['change_summary']['items'][0]['after'] ?? null)->toBe($beforeEmail === null ? 'None' : (string) $beforeEmail);
});


it('hides and rejects an older snapshot Undo after the same row changes again', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $service = app(AdminSettingsService::class);
    $settings = PublicContentSetting::general();
    $settings = $service->updatePublicContent($settings, ['public_email' => 'first@example.test']);
    $firstEvent = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();
    $firstReceipt = AdminActionReceipt::query()->where('audit_event_id', $firstEvent->getKey())->firstOrFail();

    $service->updatePublicContent($settings, ['public_email' => 'second@example.test']);
    $secondEvent = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    $feed = app(AdminActivityFeed::class);
    $first = $feed->event((int) $firstEvent->getKey(), $actor);
    $second = $feed->event((int) $secondEvent->getKey(), $actor);

    expect($first['undo'] ?? null)->toBeNull()
        ->and($second['undo'] ?? null)->not->toBeNull();

    $eventsBefore = AuditEvent::query()->count();
    $receiptsBefore = AdminActionReceipt::query()->count();

    expect(fn () => app(AdminUndoService::class)->undo((int) $firstReceipt->getKey()))
        ->toThrow(ValidationException::class, 'Undo is no longer available because this action or related data changed afterwards.');

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('second@example.test')
        ->and($firstReceipt->fresh()?->getAttribute('undone_at'))->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($eventsBefore)
        ->and(AdminActionReceipt::query()->count())->toBe($receiptsBefore)
        ->and(AuditEvent::query()->where('action', 'admin.undo_applied')->count())->toBe(0);
});


it('rejects an Undo from another administrator or an expired receipt without changing history', function (): void {
    $owner = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($owner, 'web');

    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'public_email' => 'owner-change@example.test',
    ]);
    $event = AuditEvent::query()->where('action', 'public_content_setting.updated')
        ->latest('id')->firstOrFail();
    $receipt = AdminActionReceipt::query()->where('audit_event_id', $event->getKey())->firstOrFail();
    $auditsBefore = AuditEvent::query()->count();
    $receiptsBefore = AdminActionReceipt::query()->count();

    $this->actingAs($otherAdmin, 'web');
    expect(fn () => app(AdminUndoService::class)->undo((int) $receipt->getKey()))
        ->toThrow(AuthorizationException::class);
    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('owner-change@example.test')
        ->and($receipt->fresh()?->getAttribute('undone_at'))->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);

    $this->actingAs($owner, 'web');
    $receipt->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(fn () => app(AdminUndoService::class)->undo((int) $receipt->getKey()))
        ->toThrow(ValidationException::class, 'This Undo receipt has expired.');

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('owner-change@example.test')
        ->and($receipt->fresh()?->getAttribute('undone_at'))->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminActionReceipt::query()->count())->toBe($receiptsBefore)
        ->and(AuditEvent::query()->where('action', 'admin.undo_applied')->count())->toBe(0);
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
