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
