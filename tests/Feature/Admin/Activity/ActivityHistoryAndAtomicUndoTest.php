<?php

use App\Domain\Admin\AdminUndoService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;

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
    expect($updated->getAttribute('name'))->toBe('Renamed Gallery')
        ->and($section->fresh()?->getAttribute('title'))->toBe('Renamed Gallery');

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($gallery->fresh()?->getAttribute('name'))->toBe('Original Gallery')
        ->and($section->fresh()?->getAttribute('title'))->toBe('Original Gallery')
        ->and($section->fresh()?->getAttribute('navigation_label'))->toBe('Original Gallery');
});


/** A rename spanning the Gallery and its linked navigation row. */
function activityAtomicRenameFixture(): array
{
    $actor = User::factory()->admin()->create();
    test()->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $service = app(GalleryEditorialService::class);
    $gallery = $service->create(['name' => 'Atomic original', 'slug' => 'activity-atomic-gallery', 'description' => null]);
    $section = SiteSection::query()->where('artwork_category_id', $gallery->id)->firstOrFail();
    $service->update($gallery, ['name' => 'Atomic renamed', 'description' => null]);
    $event = AuditEvent::query()->where('action', 'artwork_category.updated')->where('entity_id', $gallery->id)->latest('id')->firstOrFail();
    $receipt = AdminActionReceipt::query()->where('audit_event_id', $event->id)->firstOrFail();
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    return [$actor, $gallery, $section, $event, $receipt];
}

it('undoes a mounted atomic rename by appending history and notifying once while a repeat cannot report success', function (): void {
    [$actor, $gallery, $section, $event, $receipt] = activityAtomicRenameFixture();
    $before = $event->getRawOriginal();
    $audits = AuditEvent::query()->count();
    $page = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)
        ->mountAction('undoActivity', ['id' => $event->id])->callMountedAction()
        ->assertDispatched('admin-header-notification', fn (string $name, array $params): bool => ($params['notification']['status'] ?? null) === 'success');
    $inverse = AuditEvent::query()->latest('id')->firstOrFail();
    expect($gallery->fresh()->name)->toBe('Atomic original')
        ->and($section->fresh()->title)->toBe('Atomic original')
        ->and($section->fresh()->navigation_label)->toBe('Atomic original')
        ->and($receipt->fresh()->undone_at)->not->toBeNull()
        ->and($event->fresh()->getRawOriginal())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits + 1)
        ->and($inverse->action)->toBe('admin.undo_applied')
        ->and($inverse->metadata['source_audit_event_id'] ?? null)->toBe($event->id)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($page->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $page->mountAction('undoActivity', ['id' => $event->id])->callMountedAction()
        ->assertNotDispatched('admin-header-notification', fn (string $name, array $params): bool => ($params['notification']['status'] ?? null) === 'success');
    expect(AuditEvent::query()->count())->toBe($audits + 1)
        ->and(\App\Models\AdminNotification::query()->where('status', 'success')->count())->toBe(1)
        ->and($gallery->fresh()->name)->toBe('Atomic original');
});

it('keeps a later rename intact when a previously mounted Undo becomes stale', function (): void {
    [$actor, $gallery, $section, $event, $receipt] = activityAtomicRenameFixture();
    $page = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->mountAction('undoActivity', ['id' => $event->id]);
    app(GalleryEditorialService::class)->update($gallery->fresh(), ['name' => 'Concurrent rename', 'description' => null]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $audits = AuditEvent::query()->count();
    $page->callMountedAction()->assertNotDispatched('admin-header-notification', fn (string $name, array $params): bool => ($params['notification']['status'] ?? null) === 'success');
    expect($gallery->fresh()->name)->toBe('Concurrent rename')
        ->and($section->fresh()->title)->toBe('Concurrent rename')
        ->and($receipt->fresh()->undone_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->where('status', 'success')->count())->toBe(0);
});

it('rolls back every restored row and the receipt when Undo auditing fails then retries cleanly', function (): void {
    [$actor, $gallery, $section, $event, $receipt] = activityAtomicRenameFixture();
    $before = $event->getRawOriginal();
    $audits = AuditEvent::query()->count();
    $page = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->mountAction('undoActivity', ['id' => $event->id]);
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->once()->withArgs(fn ($actor, $action): bool => $action === 'admin.undo_applied')
        ->andThrow(new RuntimeException('Undo audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    try {
        expect(fn () => $page->callMountedAction())->toThrow(RuntimeException::class, 'Undo audit unavailable');
        expect($gallery->fresh()->name)->toBe('Atomic renamed')
            ->and($section->fresh()->title)->toBe('Atomic renamed')
            ->and($section->fresh()->navigation_label)->toBe('Atomic renamed')
            ->and($receipt->fresh()->undone_at)->toBeNull()
            ->and($event->fresh()->getRawOriginal())->toBe($before)
            ->and(AuditEvent::query()->count())->toBe($audits)
            ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
    } finally {
        app()->instance(\App\Domain\Admin\AdminAuditService::class, $realAudit);
    }
    \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->mountAction('undoActivity', ['id' => $event->id])
        ->callMountedAction()->assertDispatched('admin-header-notification');
    expect($gallery->fresh()->name)->toBe('Atomic original')
        ->and($section->fresh()->title)->toBe('Atomic original')
        ->and($receipt->fresh()->undone_at)->not->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($audits + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('shows shared history but never gives another administrator ownership of an Undo receipt', function (): void {
    [$actor, $gallery, $section, $event, $receipt] = activityAtomicRenameFixture();
    $other = User::factory()->admin()->create();
    $this->actingAs($other, 'web');
    $projected = app(AdminActivityFeed::class)->event($event->id, $other);
    expect($projected['action_key'])->toBe('artwork_category.updated')->and($projected['undo'])->toBeNull();
    $audits = AuditEvent::query()->count();
    expect(fn () => app(AdminUndoService::class)->undo($receipt->id))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->mountAction('undoActivity', ['id' => $event->id])
        ->callMountedAction()->assertNotDispatched('admin-header-notification', fn (string $name, array $params): bool => ($params['notification']['status'] ?? null) === 'success');
    expect($gallery->fresh()->name)->toBe('Atomic renamed')
        ->and($section->fresh()->title)->toBe('Atomic renamed')
        ->and($receipt->fresh()->undone_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->where('status', 'success')->count())->toBe(0);
});


it('rejects an already mounted Undo after its administrator logs out or loses access', function (bool $guest): void {
    [$actor, $gallery, $section, $event, $receipt] = activityAtomicRenameFixture();
    $page = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->mountAction('undoActivity', ['id' => $event->id]);
    $audits = AuditEvent::query()->count();
    if ($guest) { \Illuminate\Support\Facades\Auth::guard('web')->logout(); }
    else { $actor->forceFill(['is_admin' => false])->save(); }
    $page->callMountedAction()->assertForbidden();
    expect($gallery->fresh()->name)->toBe('Atomic renamed')
        ->and($section->fresh()->title)->toBe('Atomic renamed')
        ->and($receipt->fresh()->undone_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['guest' => true, 'revoked admin' => false]);
