<?php

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActivityOrderingProjection;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
});

it('reduces a reorder cycle to no visible Activity when the net permutation is identity', function (): void {
    $editor = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $editor->createCustomPage('Cycle A', 'cycle-a');
    $middle = $editor->createCustomPage('Cycle B', 'cycle-b');
    $editor->createCustomPage('Cycle C', 'cycle-c');

    $rawBefore = AuditEvent::query()->where('action', 'site_section.reordered')->count();
    $baseTime = now();

    Carbon::setTestNow($baseTime);
    expect($order->move($middle, 'down'))->toBeTrue();

    Carbon::setTestNow($baseTime->copy()->addDays(2));
    expect($order->move($middle, 'up'))->toBeTrue();
    Carbon::setTestNow();

    $rawEvents = AuditEvent::query()
        ->where('action', 'site_section.reordered')
        ->count();
    $cycleFirstEventId = (int) AuditEvent::query()
        ->where('action', 'site_section.reordered')
        ->orderBy('id')
        ->skip($rawBefore)
        ->value('id');

    $feed = app(AdminActivityFeed::class);
    $orderingRows = collect($feed->page(
        family: 'ordering',
        perPage: 100,
        actor: $this->actor,
        days: 7,
    )['activity'])->where('action_key', 'site_section.reordered')->values();

    expect($rawEvents - $rawBefore)->toBe(2)
        ->and(AdminActivityOrderingProjection::query()->count())->toBe(1)
        ->and(AdminActivityOrderingProjection::query()->firstOrFail()->isIdentity())->toBeTrue()
        ->and($orderingRows)->toHaveCount(0)
        ->and($feed->overview(family: 'ordering', days: 7)['total'])->toBe(0)
        ->and($feed->event($cycleFirstEventId, $this->actor))->toBeNull();

    expect($order->move($middle, 'down'))->toBeTrue();

    $projections = AdminActivityOrderingProjection::query()->orderBy('id')->get();
    $orderingRows = collect($feed->page(
        family: 'ordering',
        perPage: 100,
        actor: $this->actor,
        days: 7,
    )['activity'])->where('action_key', 'site_section.reordered')->values();

    expect($projections)->toHaveCount(2)
        ->and($projections[0]->isIdentity())->toBeTrue()
        ->and($projections[1]->isIdentity())->toBeFalse()
        ->and($orderingRows)->toHaveCount(1)
        ->and($orderingRows[0]['target'])->toBe('Public navigation')
        ->and($orderingRows[0]['action'])->toBe('Reordered public navigation')
        ->and($feed->overview(family: 'ordering', days: 7)['total'])->toBe(1);
});

it('freezes a non-identity ordering projection at a publication boundary', function (): void {
    $editor = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $editor->createCustomPage('Commit A', 'commit-a');
    $middle = $editor->createCustomPage('Commit B', 'commit-b');
    $editor->createCustomPage('Commit C', 'commit-c');
    $editor->createCustomPage('Commit D', 'commit-d');

    expect($order->move($middle, 'down'))->toBeTrue();

    $firstEvent = AuditEvent::query()
        ->where('action', 'site_section.reordered')
        ->latest('id')
        ->firstOrFail();
    $checkpoint = PublicationCheckpoint::query()->create([
        'admin_user_id' => $this->actor->getKey(),
        'message' => 'Ordering boundary',
        'change_count' => 1,
        'published_at' => now(),
    ]);
    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $checkpoint->getKey(),
        'audit_event_id' => $firstEvent->getKey(),
        'created_at' => now(),
    ]);

    expect($order->move($middle, 'down'))->toBeTrue();

    $projections = AdminActivityOrderingProjection::query()
        ->where('action', 'site_section.reordered')
        ->orderBy('id')
        ->get();
    $rows = collect(app(AdminActivityFeed::class)->page(
        family: 'ordering',
        perPage: 100,
        actor: $this->actor,
        days: 7,
    )['activity'])->where('action_key', 'site_section.reordered')->values();

    expect($projections)->toHaveCount(2)
        ->and($projections[0]->isIdentity())->toBeFalse()
        ->and($projections[1]->isIdentity())->toBeFalse()
        ->and($rows)->toHaveCount(2);
});

it('records one logical Journal reorder event even when several row positions change', function (): void {
    $journal = app(SiteSectionEditorialService::class)->createJournal(
        'Ordering Journal',
        'ordering-journal',
        JournalTemplate::Blog->value,
    );
    $blog = app(BlogEditorialService::class);

    $first = $blog->createDraft([
        'site_section_id' => (int) $journal->getKey(),
        'title' => 'First ordering post',
        'slug' => 'first-ordering-post',
    ]);
    $blog->createDraft([
        'site_section_id' => (int) $journal->getKey(),
        'title' => 'Second ordering post',
        'slug' => 'second-ordering-post',
    ]);
    $blog->createDraft([
        'site_section_id' => (int) $journal->getKey(),
        'title' => 'Third ordering post',
        'slug' => 'third-ordering-post',
    ]);

    $rawBefore = AuditEvent::query()->where('action', 'blog_post.reordered')->count();

    expect(app(JournalEntryOrderService::class)->moveToPosition($first, 0))->toBeTrue();

    $newEvents = AuditEvent::query()
        ->where('action', 'blog_post.reordered')
        ->where('id', '>', 0)
        ->count() - $rawBefore;

    $activityRows = collect(app(AdminActivityFeed::class)->page(
        family: 'ordering',
        perPage: 100,
        actor: $this->actor,
        days: 7,
    )['activity'])->where('action_key', 'blog_post.reordered')->values();

    expect($newEvents)->toBe(1)
        ->and($activityRows)->toHaveCount(1)
        ->and($activityRows[0]['ordering_projection']['event_count'] ?? null)->toBe(1);
});
