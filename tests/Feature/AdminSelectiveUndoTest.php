<?php

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminActionReceiptService;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Admin\EditorialRecordService;
use App\Filament\Pages\Activity;
use App\Models\AdminActionReceipt;
use App\Models\AdminActionStat;
use App\Models\AuditEvent;
use App\Models\CvEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function selectiveUndoCvEntry(string $title = 'Undo lifecycle'): CvEntry
{
    return CvEntry::create([
        'section' => 'Biography',
        'title' => $title,
        'year_text' => '2026',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'year',
    ]);
}

it('creates a bounded receipt for a supported lifecycle transition and undoes it through the domain service', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $entry = selectiveUndoCvEntry();
    $editorial = app(EditorialRecordService::class);

    expect($editorial->publish($entry)->getAttribute('state'))->toBe('published');

    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()->where('action_key', 'cv_entry.published')->firstOrFail();

    expect($receipt->getAttribute('admin_user_id'))->toBe($admin->getKey())
        ->and($receipt->getAttribute('entity_type'))->toBe('cv_entry')
        ->and($receipt->getAttribute('entity_id'))->toBe($entry->getKey())
        ->and($receipt->getAttribute('before_state'))->toBe('draft')
        ->and($receipt->getAttribute('after_state'))->toBe('published')
        ->and($receipt->getAttribute('inverse_action_key'))->toBe('cv_entry.unpublished')
        ->and($receipt->getAttribute('expires_at')->isFuture())->toBeTrue();

    $result = app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($result['inverse'])->toBe('Unpublished Vita entry')
        ->and($entry->fresh()->getAttribute('state'))->toBe('draft')
        ->and($receipt->fresh()->getAttribute('undone_at'))->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'cv_entry.unpublished')->count())->toBe(1)
        ->and(AdminActionStat::query()
            ->where('admin_user_id', $admin->getKey())
            ->where('action_key', 'cv_entry.unpublished')
            ->value('use_count'))->toBe(1)
        ->and(AdminActionReceipt::query()
            ->where('action_key', 'cv_entry.unpublished')
            ->whereNull('undone_at')
            ->where('before_state', 'published')
            ->where('after_state', 'draft')
            ->exists())->toBeTrue();
});

it('removes Undo availability when later state changes conflict with the receipt', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $entry = selectiveUndoCvEntry('Conflict-safe undo');
    $editorial = app(EditorialRecordService::class);

    $editorial->publish($entry);
    /** @var AdminActionReceipt $publishReceipt */
    $publishReceipt = AdminActionReceipt::query()->where('action_key', 'cv_entry.published')->firstOrFail();
    /** @var AuditEvent $publishEvent */
    $publishEvent = AuditEvent::query()->where('action', 'cv_entry.published')->firstOrFail();

    expect(app(AdminActivityFeed::class)->page(actor: $admin)['activity'][0]['undo'])->not->toBeNull();

    $editorial->unpublish($entry);
    $available = app(AdminActionReceiptService::class)->availableForEvents(collect([$publishEvent]), $admin);

    expect($available)->toBe([])
        ->and(fn () => app(AdminUndoService::class)->undo((int) $publishReceipt->getKey()))
        ->toThrow(ValidationException::class)
        ->and($entry->fresh()->getAttribute('state'))->toBe('draft');
});

it('rejects expired and foreign receipts without mutating the target', function (): void {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $entry = selectiveUndoCvEntry('Protected undo');
    $this->actingAs($owner, 'web');
    app(EditorialRecordService::class)->publish($entry);
    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()->where('action_key', 'cv_entry.published')->firstOrFail();

    $this->actingAs($other, 'web');
    expect(fn () => app(AdminUndoService::class)->undo((int) $receipt->getKey()))
        ->toThrow(AuthorizationException::class)
        ->and($entry->fresh()->getAttribute('state'))->toBe('published');

    $this->actingAs($owner, 'web');
    $receipt->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(AdminUndoService::class)->undo((int) $receipt->getKey()))
        ->toThrow(ValidationException::class)
        ->and($entry->fresh()->getAttribute('state'))->toBe('published');
});

it('does not create receipts for lifecycle actions without an unambiguous inverse contract', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $entry = selectiveUndoCvEntry('Archive without snapshot');

    app(EditorialRecordService::class)->archive($entry);

    expect($entry->fresh()->getAttribute('state'))->toBe('archived')
        ->and(AuditEvent::query()->where('action', 'cv_entry.archived')->count())->toBe(1)
        ->and(AdminActionReceipt::query()->count())->toBe(0);
});

it('renders Undo only for a currently eligible Activity item', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $entry = selectiveUndoCvEntry('Activity undo');
    $editorial = app(EditorialRecordService::class);

    $editorial->publish($entry);

    $this->get(Activity::getUrl())
        ->assertSuccessful()
        ->assertSee('Undo')
        ->assertSee('Published Vita entry');

    $editorial->unpublish($entry);
    /** @var AuditEvent $publishEvent */
    $publishEvent = AuditEvent::query()->where('action', 'cv_entry.published')->firstOrFail();
    $available = app(AdminActionReceiptService::class)->availableForEvents(collect([$publishEvent]), $admin);

    expect($available)->toBe([]);
});

it('caps reversible receipts per admin without deleting immutable audit events', function (): void {
    $admin = User::factory()->admin()->create();
    $service = app(AdminActionReceiptService::class);
    $entry = selectiveUndoCvEntry('Receipt retention');

    for ($index = 0; $index <= AdminActionReceiptService::MAX_RECEIPTS_PER_USER; $index++) {
        $event = AuditEvent::create([
            'admin_user_id' => $admin->getKey(),
            'action' => 'cv_entry.published',
            'entity_type' => 'cv_entry',
            'entity_id' => $entry->getKey(),
            'occurred_at' => now()->subSeconds(AdminActionReceiptService::MAX_RECEIPTS_PER_USER - $index),
        ]);

        $service->recordStateTransition(
            $event,
            $admin,
            $entry,
            'draft',
            'published',
            'cv_entry.unpublished',
        );
    }

    expect(AdminActionReceipt::query()->where('admin_user_id', $admin->getKey())->count())
        ->toBe(AdminActionReceiptService::MAX_RECEIPTS_PER_USER)
        ->and(AuditEvent::query()->where('admin_user_id', $admin->getKey())->count())
        ->toBe(AdminActionReceiptService::MAX_RECEIPTS_PER_USER + 1);
});
