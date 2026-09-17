<?php

use App\Domain\Media\MediaCapacityService;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('site-storage-accounting-test');
    config([
        'media.disk' => 'site-storage-accounting-test',
        'media.quota_bytes' => 10_000_000_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
});

it('counts media variants and logical database data against the site allowance', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    Storage::disk(config('media.disk'))->put('originals/original.jpg', str_repeat('o', 100));
    Storage::disk(config('media.disk'))->put('variants/thumbnail.jpg', str_repeat('v', 40));

    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting',
        'entity_id' => 1,
        'occurred_at' => now(),
        'request_id' => null,
        'metadata' => null,
    ]);

    AdminActionReceipt::query()->create([
        'audit_event_id' => $event->getKey(),
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
        'created_at' => now(),
    ]);

    $snapshot = app(MediaCapacityService::class)->refreshCachedSnapshot();
    $database = $snapshot['database'];

    expect($snapshot['authoritative_bytes'])->toBe(100)
        ->and($snapshot['generated_bytes'])->toBe(40)
        ->and($snapshot['database_bytes'])->toBeGreaterThan(0)
        ->and($snapshot['site_used_bytes'])->toBe(
            $snapshot['authoritative_bytes'] + $snapshot['generated_bytes'] + $snapshot['database_bytes'],
        )
        ->and($snapshot['remaining_bytes'])->toBe(
            $snapshot['quota_bytes'] - $snapshot['site_used_bytes'],
        )
        ->and($snapshot['reclaimable_bytes'])->toBe(40)
        ->and($database['activity_bytes'])->toBeGreaterThan(0)
        ->and($database['undo_bytes'])->toBeGreaterThan(0);

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Site used')
        ->assertSee('Reclaimable')
        ->assertSee('Live database')
        ->assertSee('Activity history')
        ->assertSee('Undo history')
        ->assertSee('Publication history')
        ->assertSee('Physical database footprint');
});

it('uses total site usage when admitting a new original', function (): void {
    Storage::disk(config('media.disk'))->put('originals/current.jpg', str_repeat('o', 100));
    Storage::disk(config('media.disk'))->put('variants/current-thumb.jpg', str_repeat('v', 100));

    $service = app(MediaCapacityService::class);
    $snapshot = $service->refreshCachedSnapshot();

    config(['media.quota_bytes' => $snapshot['site_used_bytes'] + 50]);

    expect(fn () => $service->assertCanStoreOriginal(51))
        ->toThrow(ValidationException::class);
});
