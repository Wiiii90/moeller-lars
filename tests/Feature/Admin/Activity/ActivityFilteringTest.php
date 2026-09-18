<?php

use App\Filament\Support\AdminActivityFeed;
use App\Filament\Support\AdminPublicationHistory;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters Activity by time without requiring a date', function (): void {
    $actor = User::factory()->admin()->create();
    $day = CarbonImmutable::today()->subDay();

    $atFourteen = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'blog_setting.updated',
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'occurred_at' => $day->setTime(14, 15),
        'request_id' => null,
        'metadata' => null,
    ]);

    AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'blog_setting.updated',
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'occurred_at' => $day->setTime(15, 45),
        'request_id' => null,
        'metadata' => null,
    ]);

    $feed = app(AdminActivityFeed::class);
    $overview = $feed->overview(hour: 14);
    $page = $feed->page(actor: $actor, hour: 14);

    expect($overview['total'])->toBe(1)
        ->and(collect($page['activity'])->pluck('id')->all())->toBe([(int) $atFourteen->getKey()]);
});

it('applies Activity filters to commit history and commit timeline data', function (): void {
    $actor = User::factory()->admin()->create(['name' => 'Commit Filter Actor']);
    $date = CarbonImmutable::parse('2025-10-01 00:00:00');

    $blogEvent = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'blog_setting.updated',
        'entity_type' => 'blog_setting',
        'entity_id' => 1,
        'occurred_at' => $date->setTime(9, 45),
        'request_id' => null,
        'metadata' => null,
    ]);
    $mediaEvent = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'media.ingested',
        'entity_type' => 'media_asset',
        'entity_id' => 1,
        'occurred_at' => $date->setTime(10, 45),
        'request_id' => null,
        'metadata' => null,
    ]);

    $blogCommit = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Blog release',
        'change_count' => 2,
        'published_at' => $date->setTime(10, 10),
    ]);
    $mediaCommit = PublicationCheckpoint::query()->create([
        'admin_user_id' => $actor->getKey(),
        'message' => 'Media release',
        'change_count' => 3,
        'published_at' => $date->setTime(11, 10),
    ]);

    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $blogCommit->getKey(),
        'audit_event_id' => $blogEvent->getKey(),
        'created_at' => $date->setTime(10, 10),
    ]);
    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $mediaCommit->getKey(),
        'audit_event_id' => $mediaEvent->getKey(),
        'created_at' => $date->setTime(11, 10),
    ]);

    $history = app(AdminPublicationHistory::class);

    $blog = $history->overview(area: 'Blog', date: '2025-10-01');
    $ten = $history->overview(date: '2025-10-01', hour: 10);
    $search = $history->page(search: 'Media release', date: '2025-10-01');

    expect($blog['total'])->toBe(1)
        ->and($blog['changes'])->toBe(2)
        ->and($blog['events'])->toBe(1)
        ->and($ten['total'])->toBe(1)
        ->and($ten['changes'])->toBe(2)
        ->and(collect($search['commits'])->pluck('id')->all())->toBe([(int) $mediaCommit->getKey()]);
});

it('uses the shared Activity page-size contract for activity and commit history', function (): void {
    $actor = User::factory()->admin()->create();

    $activity = app(AdminActivityFeed::class)->page(perPage: 25, actor: $actor);
    $history = app(AdminPublicationHistory::class)->page(perPage: 100);
    $clampedHistory = app(AdminPublicationHistory::class)->page(perPage: 500);

    expect($activity['paginator']->perPage())->toBe(25)
        ->and($history['paginator']->perPage())->toBe(100)
        ->and($clampedHistory['paginator']->perPage())->toBe(100);
});
