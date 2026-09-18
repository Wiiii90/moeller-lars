<?php

use App\Domain\Admin\AdminActionReceiptService;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the default Activity feed complete beyond the former 180 day window', function (): void {
    $actor = User::factory()->admin()->create();
    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'public_content_setting.updated',
        'entity_type' => 'public_content_setting',
        'entity_id' => 1,
        'occurred_at' => now()->subDays(400),
        'request_id' => null,
        'metadata' => null,
    ]);

    $feed = app(AdminActivityFeed::class);
    $page = $feed->page(perPage: 100, actor: $actor);
    $overview = $feed->overview();

    expect(collect($page['activity'])->pluck('id')->all())->toContain((int) $event->getKey())
        ->and($overview['total'])->toBeGreaterThanOrEqual(1);
});

it('treats a Gallery rename and its linked page update as one atomic Undo', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    $service = app(GalleryEditorialService::class);
    $gallery = $service->create([
        'name' => 'Original Gallery',
        'slug' => 'original-gallery',
        'description' => null,
    ]);
    $section = SiteSection::query()->where('artwork_category_id', $gallery->getKey())->firstOrFail();

    $updated = $service->update($gallery, [
        'name' => 'Renamed Gallery',
        'description' => null,
    ]);

    $event = AuditEvent::query()
        ->where('action', 'artwork_category.updated')
        ->where('entity_id', $gallery->getKey())
        ->latest('id')
        ->firstOrFail();
    $receipt = AdminActionReceipt::query()->where('audit_event_id', $event->getKey())->firstOrFail();
    $payload = $receipt->getAttribute('snapshot_payload');

    expect($updated->getAttribute('name'))->toBe('Renamed Gallery')
        ->and($section->fresh()?->getAttribute('title'))->toBe('Renamed Gallery')
        ->and($payload)->toBeArray()
        ->and($payload['rows'] ?? null)->toBeArray()
        ->and(collect($payload['rows'])->pluck('table')->all())->toContain('artwork_categories', 'site_sections');

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($gallery->fresh()?->getAttribute('name'))->toBe('Original Gallery')
        ->and($section->fresh()?->getAttribute('title'))->toBe('Original Gallery')
        ->and($section->fresh()?->getAttribute('navigation_label'))->toBe('Original Gallery');
});

it('keeps a large but bounded Undo receipt window', function (): void {
    expect(AdminActionReceiptService::RETENTION_DAYS)->toBe(365)
        ->and(AdminActionReceiptService::MAX_RECEIPTS_PER_USER)->toBe(5000);
});
