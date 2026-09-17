<?php

use App\Domain\Content\JournalEntryOrderService;
use App\Models\BlogPost;
use App\Models\SiteSection;

it('reserves position one for a new Journal entry and keeps existing order contiguous', function (): void {
    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Blog',
        'navigation_label' => 'Blog',
        'slug' => 'blog-ordering-test',
        'state' => 'draft',
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
