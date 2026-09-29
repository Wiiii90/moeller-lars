<?php

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminActivityOrderingGroup;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
});

it('keeps raw reorder history while projecting an identity permutation cycle as one Activity row', function (): void {
    $editor = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);

    $editor->createCustomPage('Cycle A', 'cycle-a');
    $middle = $editor->createCustomPage('Cycle B', 'cycle-b');
    $editor->createCustomPage('Cycle C', 'cycle-c');

    $rawBefore = AuditEvent::query()->where('action', 'site_section.reordered')->count();

    expect($order->move($middle, 'down'))->toBeTrue()
        ->and($order->move($middle, 'up'))->toBeTrue();

    $rawEvents = AuditEvent::query()
        ->where('action', 'site_section.reordered')
        ->where('id', '>', 0)
        ->count();

    $orderingRows = collect(app(AdminActivityFeed::class)->page(
        family: 'ordering',
        perPage: 100,
        actor: $this->actor,
        days: 7,
    )['activity'])->where('action_key', 'site_section.reordered')->values();

    expect($rawEvents - $rawBefore)->toBe(2)
        ->and(AdminActivityOrderingGroup::query()->count())->toBe(1)
        ->and($orderingRows)->toHaveCount(1)
        ->and($orderingRows[0]['ordering_group']['event_count'] ?? null)->toBe(2)
        ->and($orderingRows[0]['ordering_group']['returned_to_identity'] ?? false)->toBeTrue()
        ->and($orderingRows[0]['undo'] ?? null)->toBeNull()
        ->and($orderingRows[0]['action'])->toContain('returned to starting order after 2 changes');
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
        ->and($activityRows[0]['ordering_group']['event_count'] ?? null)->toBe(1);
});
