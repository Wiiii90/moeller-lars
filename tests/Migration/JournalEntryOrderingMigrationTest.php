<?php

use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;

it('re-sequences existing Blog and Exhibition journals to newest-first defaults', function (): void {
    // This historical migration runs before the partial-date schema cutover.
    // Recreate only its removed authored field inside the isolated test transaction.
    DB::statement('ALTER TABLE public.exhibitions ADD COLUMN date_text VARCHAR(160) NULL');
    $blog = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Blog',
        'navigation_label' => 'Blog',
        'slug' => 'migration-blog-order',
        'state' => 'hidden',
        'position' => 200,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $exhibitions = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS,
        'title' => 'Exhibitions',
        'navigation_label' => 'Exhibitions',
        'slug' => 'migration-exhibition-order',
        'state' => 'hidden',
        'position' => 210,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    BlogPost::query()->create([
        'site_section_id' => $blog->getKey(),
        'slug' => 'older-published',
        'title' => 'Older published',
        'body' => 'Older published body',
        'state' => 'published',
        'position' => 0,
        'published_at' => '2024-01-01 12:00:00+00',
    ]);
    BlogPost::query()->create([
        'site_section_id' => $blog->getKey(),
        'slug' => 'draft-first',
        'title' => 'Draft first',
        'state' => 'draft',
        'position' => 1,
    ]);
    BlogPost::query()->create([
        'site_section_id' => $blog->getKey(),
        'slug' => 'newer-published',
        'title' => 'Newer published',
        'body' => 'Newer published body',
        'state' => 'published',
        'position' => 2,
        'published_at' => '2025-01-01 12:00:00+00',
    ]);

    Exhibition::query()->create([
        'site_section_id' => $exhibitions->getKey(),
        'slug' => 'event-2012',
        'title' => 'Event 2012',
        'state' => 'draft',
        'position' => 0,
        'starts_on' => null,
    ]);
    Exhibition::query()->create([
        'site_section_id' => $exhibitions->getKey(),
        'slug' => 'event-2014',
        'title' => 'Event 2014',
        'state' => 'draft',
        'position' => 1,
        'starts_on' => null,
    ]);
    Exhibition::query()->create([
        'site_section_id' => $exhibitions->getKey(),
        'slug' => 'event-2015',
        'title' => 'Event 2015',
        'state' => 'draft',
        'position' => 2,
        'starts_on' => null,
    ]);

    DB::table('exhibitions')->where('slug', 'event-2012')->update(['date_text' => '2012']);
    DB::table('exhibitions')->where('slug', 'event-2014')->update(['date_text' => 'June 2014']);
    DB::table('exhibitions')->where('slug', 'event-2015')->update(['date_text' => '30.04.2015 – 27.06.2015']);

    $migration = require database_path('migrations/2026_09_17_000001_resequence_journal_entries_newest_first.php');
    $migration->up();

    expect(BlogPost::query()->where('site_section_id', $blog->getKey())->orderBy('position')->pluck('title')->all())
        ->toBe(['Draft first', 'Newer published', 'Older published'])
        ->and(BlogPost::query()->where('site_section_id', $blog->getKey())->orderBy('position')->pluck('position')->all())
        ->toBe([1, 2, 3])
        ->and(Exhibition::query()->where('site_section_id', $exhibitions->getKey())->orderBy('position')->pluck('title')->all())
        ->toBe(['Event 2015', 'Event 2014', 'Event 2012'])
        ->and(Exhibition::query()->where('site_section_id', $exhibitions->getKey())->orderBy('position')->pluck('position')->all())
        ->toBe([1, 2, 3]);
});
