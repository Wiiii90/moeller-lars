<?php

use App\Domain\Admin\AdminActivityFeed;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicationEventState;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function activityReconciliationEvent(
    User $actor,
    string $action,
    CarbonInterface $occurredAt,
    string $entityType = 'blog_setting',
    int $entityId = 1,
): AuditEvent {
    return AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'occurred_at' => $occurredAt,
        'request_id' => null,
        'metadata' => null,
    ]);
}

it('uses the shared Visual Stage plus central Activity table and pager', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/activity.blade.php'));
    $activityCss = (string) file_get_contents(resource_path('css/admin/activity.css'));
    $dataCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));

    expect($view)->toContain(
        'activity-atlas__grid admin-visual-stage admin-visual-stage--stackable',
        'activity-atlas__visual admin-visual-stage__pane',
        'activity-publication admin-visual-stage__pane',
        'class="admin-visual-stage-followup"',
        '<x-admin.table class="admin-table--data activity-workspace__table">',
        '<th scope="col">Publication</th>',
        'class="admin-status is-published">Committed',
        'class="admin-status">Staged',
        'class="admin-status">No pending delta',
        'wire:click="undo(',
        'class="admin-pager"',
        'admin-pager__range',
        'admin-pager__actions admin-toolbar',
        '>Previous</a>',
        '>Next</a>',
    )
        ->and($view)->not->toContain('$paginator->links()', 'activity-pagination')
        ->and($activityCss)->not->toContain(
            '.activity-pagination',
            '.activity-action',
            '.activity-table',
            'min-height: clamp(23rem',
            'min-height: 23rem',
            'min-height: 22rem',
            'min-height: 21rem',
            'min-height: 19rem',
        )
        ->and($dataCss)->toMatch('/\.admin-visual-stage\s*\{[^}]*height:\s*var\(--admin-visual-stage-height\);/s')
        ->and($dataCss)->toMatch('/\.admin-pager\s*\{[^}]*border-top:\s*1px solid var\(--admin-line-strong\);/s')
        ->and($dataCss)->not->toMatch('/\.admin-pager\s*\{[^}]*border-bottom:/s');
});

it('keeps full-window Activity aggregates stable across table pages and applies shared filters', function (): void {
    $alice = User::factory()->admin()->create(['name' => 'Alice Activity']);
    $bob = User::factory()->admin()->create(['name' => 'Bob Activity']);

    for ($index = 0; $index < 20; $index++) {
        activityReconciliationEvent(
            $alice,
            'blog_setting.updated',
            now()->subDay()->setTime($index % 10, $index, 0),
        );
    }

    for ($index = 0; $index < 20; $index++) {
        activityReconciliationEvent(
            $bob,
            'media.ingested',
            now()->subDays(2)->setTime(12 + ($index % 8), $index, 0),
        );
    }

    $feed = app(AdminActivityFeed::class);

    request()->query->set('page', 1);
    $firstPage = $feed->page(perPage: 30, actor: $alice, days: 7);
    $firstOverview = $feed->overview(days: 7);

    request()->query->set('page', 2);
    $secondPage = $feed->page(perPage: 30, actor: $alice, days: 7);
    $secondOverview = $feed->overview(days: 7);

    expect(count($firstPage['activity']))->toBe(30)
        ->and(count($secondPage['activity']))->toBe(10)
        ->and($firstOverview['hourly'])->toBe($secondOverview['hourly'])
        ->and($firstOverview['daily'])->toBe($secondOverview['daily'])
        ->and($firstOverview['total'])->toBe(40)
        ->and($feed->overview(area: 'Blog', days: 7)['total'])->toBe(20)
        ->and($feed->overview(area: 'Media', days: 7)['total'])->toBe(20)
        ->and($feed->overview(family: 'settings', days: 7)['total'])->toBe(20)
        ->and($feed->overview(days: 7, search: 'Alice Activity')['total'])->toBe(20)
        ->and($feed->overview(days: 7, search: 'uploaded media')['total'])->toBe(20);
});

it('projects staged committed and not-pending publication states without conflating them', function (): void {
    $actor = User::factory()->admin()->create();
    $feed = app(AdminActivityFeed::class);

    $staged = activityReconciliationEvent($actor, 'blog_setting.updated', now()->subMinutes(3));
    PublicationEventState::query()->create([
        'audit_event_id' => $staged->getKey(),
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'status' => PublicationEventState::STATUS_PENDING,
        'updated_at' => now(),
    ]);

    $neutral = activityReconciliationEvent($actor, 'blog_setting.updated', now()->subMinutes(2));
    PublicationEventState::query()->create([
        'audit_event_id' => $neutral->getKey(),
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'status' => PublicationEventState::STATUS_NOT_PENDING,
        'updated_at' => now(),
    ]);

    $committed = activityReconciliationEvent($actor, 'blog_setting.updated', now()->subMinute());
    PublicationEventState::query()->create([
        'audit_event_id' => $committed->getKey(),
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'status' => PublicationEventState::STATUS_PENDING,
        'updated_at' => now(),
    ]);
    $checkpoint = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Activity checkpoint',
        'change_count' => 3,
        'published_at' => now(),
    ]);
    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $checkpoint->getKey(),
        'audit_event_id' => $committed->getKey(),
        'created_at' => now(),
    ]);

    $activity = collect($feed->page(perPage: 30, actor: $actor, days: 7)['activity']);

    expect($activity->firstWhere('id', (int) $staged->getKey())['publication_status'])->toBe('pending')
        ->and($activity->firstWhere('id', (int) $neutral->getKey())['publication_status'])->toBe('not_pending')
        ->and($activity->firstWhere('id', (int) $neutral->getKey())['checkpoint_id'])->toBeNull()
        ->and($activity->firstWhere('id', (int) $committed->getKey())['publication_status'])->toBe('committed')
        ->and($activity->firstWhere('id', (int) $committed->getKey())['checkpoint_id'])->toBe((int) $checkpoint->getKey())
        ->and($activity->firstWhere('id', (int) $committed->getKey())['checkpoint_message'])->toBe('Activity checkpoint');
});

it('returns bounded current staged activity and ordered checkpoint context', function (): void {
    $actor = User::factory()->admin()->create();
    $feed = app(AdminActivityFeed::class);
    $initial = PublicationCheckpoint::query()
        ->where('message', 'Initial public state')
        ->firstOrFail();
    $initial->setAttribute('published_at', now()->subHours(2));
    $initial->save();

    $staged = activityReconciliationEvent($actor, 'blog_setting.updated', now()->subMinutes(4));
    PublicationEventState::query()->create([
        'audit_event_id' => $staged->getKey(),
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'status' => PublicationEventState::STATUS_PENDING,
        'updated_at' => now(),
    ]);

    $checkpointed = activityReconciliationEvent($actor, 'blog_setting.updated', now()->subMinutes(3));
    PublicationEventState::query()->create([
        'audit_event_id' => $checkpointed->getKey(),
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'status' => PublicationEventState::STATUS_PENDING,
        'updated_at' => now(),
    ]);

    $older = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Older checkpoint',
        'change_count' => 2,
        'published_at' => now()->subHour(),
    ]);
    $latest = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Latest checkpoint',
        'change_count' => 5,
        'published_at' => now(),
    ]);
    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $latest->getKey(),
        'audit_event_id' => $checkpointed->getKey(),
        'created_at' => now(),
    ]);

    $context = $feed->publicationContext(4);

    expect($context['staged'])->toBe(1)
        ->and($context['latest']['id'])->toBe((int) $latest->getKey())
        ->and($context['latest']['message'])->toBe('Latest checkpoint')
        ->and($context['latest']['change_count'])->toBe(5)
        ->and(array_column($context['recent'], 'id'))->toBe([
            (int) $older->getKey(),
            (int) $initial->getKey(),
        ])
        ->and(array_column($context['recent'], 'message'))->toBe([
            'Older checkpoint',
            'Initial public state',
        ]);
});

it('keeps Activity overview aggregation query count fixed as event volume grows', function (): void {
    $actor = User::factory()->admin()->create();

    for ($index = 0; $index < 80; $index++) {
        activityReconciliationEvent(
            $actor,
            $index % 2 === 0 ? 'blog_setting.updated' : 'media.ingested',
            now()->subDays($index % 6)->setTime($index % 24, $index % 60, 0),
        );
    }

    $activityQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$activityQueries): void {
        if (str_contains(strtolower($query->sql), 'audit_events')) {
            $activityQueries++;
        }
    });

    $overview = app(AdminActivityFeed::class)->overview(days: 7);

    expect($overview['total'])->toBe(80)
        ->and($activityQueries)->toBeLessThanOrEqual(5);
});
