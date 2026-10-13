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

    expect($projected['action'] ?? null)
        ->toContain('Public email')
        ->toContain('Public email visibility')
        ->toContain('precise-activity@example.test')
        ->toContain($beforeVisibility ? 'On → Off' : 'Off → On')
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
        ->and($projected['action'] ?? null)->toBe('Deleted artwork')
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
        ->and($summary['items'])->toHaveCount(12);
});
