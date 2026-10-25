<?php

use App\Domain\Admin\AdminChangeSummary;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\Artwork;
use App\Models\AuditEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('names multiple changed fields instead of a generic settings activity', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $settings = PublicContentSetting::general();
    $beforeVisibility = (bool) $settings->getAttribute('show_public_email');

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'public_email' => 'precise-activity@example.test',
        'show_public_email' => ! $beforeVisibility,
    ]);

    $event = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->latest('id')
        ->firstOrFail();
    $projected = app(AdminActivityFeed::class)->event((int) $event->getKey(), $actor);
    $items = collect($projected['change_summary']['items'] ?? [])->keyBy('label');

    expect($projected['action_key'] ?? null)->toBe('public_content_setting.updated')
        ->and($projected['change'] ?? null)->toBe('Edited website settings')
        ->and($projected['details'] ?? null)->toContain('Public email')
        ->toContain('Public email visibility')
        ->toContain('precise-activity@example.test')
        ->toContain($beforeVisibility ? '“On” → “Off”' : '“Off” → “On”')
        ->not->toBe('Edited website settings')
        ->and($items->get('Public email')['after'] ?? null)->toBe('precise-activity@example.test')
        ->and($items->get('Public email visibility')['before'] ?? null)->toBe($beforeVisibility ? 'On' : 'Off')
        ->and($items->get('Public email visibility')['after'] ?? null)->toBe($beforeVisibility ? 'Off' : 'On');
});


it('preserves the historical target label after a deleted record disappears', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $galleryId = (int) DB::table('artwork_categories')->insertGetId([
        'slug' => 'activity-delete-target-gallery',
        'name' => 'Activity delete target Gallery',
        'show_on_home' => false,
    ]);
    $artworkId = (int) DB::table('artworks')->insertGetId([
        'artwork_category_id' => $galleryId,
        'slug' => 'activity-delete-target',
        'title' => 'Vanishing Activity Artwork',
        'analytics_key' => '6d6f9c8a-7b61-4a85-8f09-7d6d5f6e3c21',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    app(ArtworkDraftService::class)->delete(Artwork::query()->findOrFail($artworkId));

    expect(Artwork::query()->whereKey($artworkId)->exists())->toBeFalse();

    $event = AuditEvent::query()
        ->where('action', 'artwork.deleted')
        ->where('entity_id', $artworkId)
        ->latest('id')
        ->firstOrFail();
    $metadata = $event->getAttribute('metadata');
    $projected = app(AdminActivityFeed::class)->event((int) $event->getKey(), $actor);

    expect($metadata['target_label'] ?? null)->toBe('Vanishing Activity Artwork')
        ->and($projected['target'] ?? null)->toBe('Vanishing Activity Artwork')
        ->and($projected['action_key'] ?? null)->toBe('artwork.deleted')
        ->and($projected['change'] ?? null)->toBe('Deleted artwork')
        ->and($projected['details'] ?? null)->toContain('Vanishing Activity Artwork')
        ->and($projected['url'] ?? null)->toBeNull();
});


it('keeps the full change count when the Activity detail payload is truncated', function (): void {
    $before = [];
    $after = [];
    foreach (range(1, 20) as $index) {
        $before['field_'.$index] = 'before-'.$index;
        $after['field_'.$index] = 'after-'.$index;
    }

    $summary = app(AdminChangeSummary::class)->summarize([[
        'entity_type' => 'public_content_setting',
        'table' => 'public_content_settings',
        'row_id' => 1,
        'before' => $before,
        'after' => $after,
    ]]);

    expect($summary)->not->toBeNull()
        ->and($summary['count'])->toBe(20)
        ->and($summary['truncated'])->toBeTrue()
        ->and($summary['items'])->not->toBeEmpty()
        ->and(count($summary['items']))->toBeLessThan($summary['count']);
});


it('renders persisted structured change details without rewriting history or notifying', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'detail-render@example.test']);
    $event = AuditEvent::query()->latest('id')->firstOrFail();
    $before = $event->fresh()->getRawOriginal();
    $audits = AuditEvent::query()->count();
    \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)
        ->mountAction('activityDetails', ['id' => $event->id])
        ->assertActionMounted('activityDetails')
        ->assertMountedActionModalSee(['Edited website settings', 'Public email', 'detail-render@example.test'])
        ->assertNotDispatched('admin-header-notification');
    expect($event->fresh()->getRawOriginal())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});

it('rejects rewriting and deleting audit records through the model', function (string $operation): void {
    $actor = User::factory()->admin()->create();
    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->id, 'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting', 'entity_id' => 1, 'occurred_at' => now(),
    ]);
    $before = $event->fresh()->getRawOriginal();
    expect(fn () => $operation === 'update' ? $event->update(['entity_id' => 2]) : $event->delete())
        ->toThrow(LogicException::class, 'append-only');
    expect($event->fresh()->getRawOriginal())->toBe($before)->and(AuditEvent::query()->count())->toBe(1);
})->with(['update', 'delete']);

it('enforces append-only audit history at the PostgreSQL boundary', function (string $operation): void {
    $actor = User::factory()->admin()->create();
    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->id, 'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting', 'entity_id' => 1, 'occurred_at' => now(),
    ]);
    $before = $event->fresh()->getRawOriginal();
    expect(fn () => DB::transaction(function () use ($event, $operation): void {
        $query = DB::table('audit_events')->where('id', $event->id);
        $operation === 'update' ? $query->update(['entity_id' => 2]) : $query->delete();
    }))->toThrow(\Illuminate\Database\QueryException::class, 'append-only');
    expect($event->fresh()->getRawOriginal())->toBe($before)->and(AuditEvent::query()->count())->toBe(1);
})->with(['update', 'delete']);
