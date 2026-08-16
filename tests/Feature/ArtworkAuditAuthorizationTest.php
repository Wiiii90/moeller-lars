<?php

use App\Domain\Admin\AdminAuditService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function auditAdmin(): User
{
    return User::factory()->admin()->create();
}

function auditEventFor(User $actor): AuditEvent
{
    return app(AdminAuditService::class)->record($actor, 'artwork.created', 'artwork', 1);
}

it('records a valid audit event', function () {
    $actor = auditAdmin();
    $event = auditEventFor($actor);

    expect($event->admin_user_id)->toBe($actor->getKey())
        ->and($event->action)->toBe('artwork.created')
        ->and($event->entity_type)->toBe('artwork')
        ->and($event->entity_id)->toBe(1)
        ->and($event->occurred_at)->not->toBeNull();
});

it('keeps Eloquent audit events append-only', function () {
    $event = auditEventFor(auditAdmin());

    expect(fn () => $event->update(['action' => 'artwork.updated']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('keeps direct database audit updates and deletes append-only', function () {
    $event = auditEventFor(auditAdmin());

    expect(fn () => DB::table('audit_events')->where('id', $event->getKey())->update(['action' => 'artwork.updated']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_events')->where('id', $event->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('allows inserts despite the append-only trigger', function () {
    expect(auditEventFor(auditAdmin())->exists)->toBeTrue();
});

it('requires an authenticated admin actor', function () {
    expect(fn () => app(AdminAuditService::class)->requireActor())->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(AdminAuditService::class)->requireActor())->toThrow(AuthorizationException::class);
});

it('rejects invalid audit action, entity type, keys, and values', function (string $case) {
    $service = app(AdminAuditService::class);
    $actor = auditAdmin();

    $callback = match ($case) {
        'action' => fn () => $service->record($actor, 'invalid.action', 'artwork', 1),
        'entity' => fn () => $service->record($actor, 'artwork.created', 'invalid', 1),
        'key' => fn () => $service->record($actor, 'artwork.created', 'artwork', 1, ['title' => 1]),
        'value' => fn () => $service->record($actor, 'artwork.created', 'artwork', 1, ['artwork_id' => 0]),
    };

    expect($callback)->toThrow(InvalidArgumentException::class);
})->with(['action', 'entity', 'key', 'value']);
