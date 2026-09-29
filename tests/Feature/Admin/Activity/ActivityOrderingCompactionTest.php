<?php

use App\Domain\Admin\AdminActivityOrderingProjector;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActivityOrderingGroup;
use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function projectedOrderingEvent(User $actor, CarbonImmutable $occurredAt): AuditEvent
{
    $id = DB::table('audit_events')->insertGetId([
        'admin_user_id' => $actor->getKey(),
        'action' => 'artwork_category.gallery_reordered',
        'entity_type' => 'artwork_category',
        'entity_id' => 999999,
        'occurred_at' => $occurredAt,
        'request_id' => null,
        'metadata' => null,
    ]);

    return AuditEvent::query()->findOrFail($id);
}

it('compacts contiguous reorder mutations and closes a sequence when it returns to identity', function (): void {
    $actor = User::factory()->admin()->create();
    $projector = app(AdminActivityOrderingProjector::class);
    $now = CarbonImmutable::parse('2026-09-29 17:00:00');

    $a = hash('sha256', 'A');
    $b = hash('sha256', 'B');
    $c = hash('sha256', 'C');

    $first = projectedOrderingEvent($actor, $now);
    $projector->record($first, [
        'scope' => 'gallery-artworks:999999',
        'target_label' => 'Test Gallery',
        'before_hash' => $a,
        'after_hash' => $b,
        'item_count' => 4,
    ]);

    $second = projectedOrderingEvent($actor, $now->addSecond());
    $projector->record($second, [
        'scope' => 'gallery-artworks:999999',
        'target_label' => 'Test Gallery',
        'before_hash' => $b,
        'after_hash' => $a,
        'item_count' => 4,
    ]);

    $third = projectedOrderingEvent($actor, $now->addSeconds(2));
    $projector->record($third, [
        'scope' => 'gallery-artworks:999999',
        'target_label' => 'Test Gallery',
        'before_hash' => $a,
        'after_hash' => $c,
        'item_count' => 4,
    ]);

    $groups = AdminActivityOrderingGroup::query()->orderBy('id')->get();

    expect(AuditEvent::query()->count())->toBe(3)
        ->and($groups)->toHaveCount(2)
        ->and((int) $groups[0]->event_count)->toBe(2)
        ->and($groups[0]->returnedToIdentity())->toBeTrue()
        ->and((int) $groups[1]->event_count)->toBe(1)
        ->and($groups[1]->returnedToIdentity())->toBeFalse();

    $feed = app(AdminActivityFeed::class);
    $page = collect($feed->page(perPage: 30, actor: $actor, days: 7)['activity']);

    expect($page)->toHaveCount(2)
        ->and($page->pluck('id')->all())->toBe([(int) $third->getKey(), (int) $second->getKey()])
        ->and($page->firstWhere('id', (int) $second->getKey())['action'])
        ->toContain('returned to starting order after 2 changes')
        ->and($page->firstWhere('id', (int) $second->getKey())['target'])->toBe('Test Gallery')
        ->and($feed->overview(days: 7)['total'])->toBe(2);

    $historicalStep = $feed->event((int) $first->getKey(), $actor);
    expect($historicalStep['id'] ?? null)->toBe((int) $second->getKey());
});
