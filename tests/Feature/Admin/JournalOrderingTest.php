<?php

use App\Domain\Content\JournalEntryOrderService;
use App\Models\AdminActivityOrderingEvent;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reserves position one for a new Journal entry and keeps existing order contiguous', function (): void {
    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Blog',
        'navigation_label' => 'Blog',
        'slug' => 'blog-ordering-test',
        'state' => 'hidden',
        'position' => 100,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $first = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'first-existing-post',
        'title' => 'First existing post',
        'body' => null,
        'state' => 'draft',
        'position' => 4,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);
    $second = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'second-existing-post',
        'title' => 'Second existing post',
        'body' => null,
        'state' => 'draft',
        'position' => 9,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);

    $position = app(JournalEntryOrderService::class)->nextPosition(new BlogPost, (int) $section->getKey());

    expect($position)->toBe(1)
        ->and(BlogPost::query()->where('site_section_id', $section->getKey())->orderBy('position')->pluck('id')->all())
        ->toBe([(int) $first->getKey(), (int) $second->getKey()])
        ->and(BlogPost::query()->where('site_section_id', $section->getKey())->orderBy('position')->pluck('position')->all())
        ->toBe([2, 3]);
});


it('records one logical Activity event when a Journal reorder rewrites several positions', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Activity Blog',
        'navigation_label' => 'Activity Blog',
        'slug' => 'activity-blog-ordering-test',
        'state' => 'hidden',
        'position' => 100,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $posts = collect(range(1, 3))->map(fn (int $position): BlogPost => BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'activity-order-post-'.$position,
        'title' => 'Activity order post '.$position,
        'body' => null,
        'state' => 'draft',
        'position' => $position,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]));

    expect(app(JournalEntryOrderService::class)->moveToPosition($posts[0], 2))->toBeTrue();

    expect(BlogPost::query()
        ->where('site_section_id', $section->getKey())
        ->orderBy('position')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all())->toBe([
            (int) $posts[1]->getKey(),
            (int) $posts[2]->getKey(),
            (int) $posts[0]->getKey(),
        ]);

    $events = AuditEvent::query()->where('action', 'blog_post.reordered')->get();
    expect($events)->toHaveCount(1)
        ->and((int) $events->first()->entity_id)->toBe((int) $posts[0]->getKey())
        ->and(AdminActivityOrderingEvent::query()->whereKey($events->first()->getKey())->exists())->toBeTrue();
});
