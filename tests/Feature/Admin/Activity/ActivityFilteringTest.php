<?php

use App\Filament\Support\AdminActivityFeed;
use App\Filament\Support\AdminPublicationHistory;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

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
        ->and($blog['activities'])->toBe(1)
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


it('combines actor search area family date and hour across pagination and refreshes the real page filters', function (): void {
    $actor = User::factory()->admin()->create(['name' => 'Website filter editor']);
    $other = User::factory()->admin()->create(['name' => 'Media filter editor']);
    $this->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $day = CarbonImmutable::today()->subDay();
    $ids = [];
    foreach (range(1, 26) as $index) {
        $ids[] = AuditEvent::query()->create([
            'admin_user_id' => $actor->id, 'action' => 'public_content_setting.updated',
            'entity_type' => 'public_content_setting', 'entity_id' => 1, 'occurred_at' => $day->setTime(14, 15),
        ])->id;
    }
    AuditEvent::query()->create([
        'admin_user_id' => $other->id, 'action' => 'media.ingested',
        'entity_type' => 'media_asset', 'entity_id' => 1, 'occurred_at' => $day->setTime(13, 15),
    ]);
    $feed = app(AdminActivityFeed::class);
    $filters = ['area' => 'Website', 'family' => 'settings', 'search' => 'Website filter editor', 'date' => $day->toDateString(), 'hour' => 14];
    $page = $feed->page(...[...$filters, 'perPage' => 25, 'page' => 2, 'actor' => $actor]);
    expect($feed->overview(...$filters)['total'])->toBe(26)
        ->and($page['paginator']->total())->toBe(26)
        ->and(array_column($page['activity'], 'id'))->toBe([$ids[0]])
        ->and($page['activity'][0]['action_key'])->toBe('public_content_setting.updated');
    \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)
        ->set('activityPage', 2)->set('selectedActivityIds', [$ids[0]])
        ->set('areaFilter', 'Website')
        ->assertSet('activityPage', 1)->assertSet('selectedActivityIds', [])
        ->assertSee('Website filter editor')->assertDontSee('Media filter editor')
        ->set('familyFilter', 'settings')->set('hourFilter', '14')
        ->set('dateFilter', $day->toDateString())
        ->assertSee('Website filter editor')->assertNotDispatched('admin-header-notification');
});

it('rejects already mounted Activity requests after administrative access is lost', function (string $mode, bool $guest): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $component = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)->call('setViewMode', $mode);
    if ($guest) { \Illuminate\Support\Facades\Auth::guard('web')->logout(); }
    else { $actor->forceFill(['is_admin' => false])->save(); }
    $component->set('search', 'Protected history')->assertForbidden();
    expect(AuditEvent::query()->count())->toBe(0)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['activity', 'commits'])->with(['guest' => true, 'revoked admin' => false]);
