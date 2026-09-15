<?php

use App\Domain\Admin\AdminAuditService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function auditAdmin(): User
{
    return User::factory()->admin()->create();
}

function auditEventFor(User $actor): AuditEvent
{
    return app(AdminAuditService::class)->record($actor, 'artwork.created', 'artwork', 1);
}

it('records the authenticated admin actor on audit events', function (): void {
    $actor = auditAdmin();
    $event = auditEventFor($actor);

    expect($event->admin_user_id)->toBe($actor->getKey())
        ->and($event->action)->toBe('artwork.created')
        ->and($event->entity_type)->toBe('artwork')
        ->and($event->entity_id)->toBe(1)
        ->and($event->occurred_at)->not->toBeNull();
});

it('keeps Eloquent audit events append-only', function (): void {
    $event = auditEventFor(auditAdmin());

    expect(fn () => $event->update(['action' => 'artwork.updated']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('keeps direct database audit mutations append-only', function (): void {
    $event = auditEventFor(auditAdmin());

    expect(fn () => DB::table('audit_events')->where('id', $event->getKey())->update(['action' => 'artwork.updated']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_events')->where('id', $event->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('requires an authenticated admin actor', function (): void {
    expect(fn () => app(AdminAuditService::class)->requireActor())->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(AdminAuditService::class)->requireActor())->toThrow(AuthorizationException::class);
});
