<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes Blog Journal routes from their owning SiteSection and excludes future posts', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $sections = app(SiteSectionEditorialService::class);
    $journal = $sections->createJournal('Dispatches', 'dispatches-seo', SiteSection::JOURNAL_TEMPLATE_BLOG);
    $sections->updatePlacement($journal, 'published', false, null);

    BlogPost::create(['site_section_id' => $journal->id, 'slug' => 'published-post', 'title' => 'Published post', 'body' => 'Safe **body**', 'state' => 'published', 'position' => 0, 'published_at' => now()]);
    BlogPost::create(['site_section_id' => $journal->id, 'slug' => 'future-post', 'title' => 'Future post', 'body' => 'Future body', 'state' => 'scheduled', 'position' => 1, 'scheduled_at' => now()->addDay()]);

    $this->get('/dispatches-seo')->assertSuccessful()->assertSee('Published post')->assertDontSee('Future post');
    $this->get('/dispatches-seo/published-post')->assertSuccessful()->assertSee('<strong>body</strong>', false);
    $this->get('/dispatches-seo/future-post')->assertNotFound();
});

it('builds the sitemap from current published page instances and omits route-less nodes', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $sections = app(SiteSectionEditorialService::class);
    $page = $sections->createCustomPage('Studio', 'studio-seo');
    $journal = $sections->createJournal('Journal', 'journal-seo', SiteSection::JOURNAL_TEMPLATE_BLOG);
    $node = $sections->createNavigationGroup('Route-less SEO node');

    $sections->updatePlacement($page, 'published', false, null);
    $sections->updatePlacement($journal, 'published', false, null);
    $sections->updatePlacement($node, 'published', true, null);

    $this->get('/sitemap.xml')->assertSuccessful()->assertSee('/studio-seo')->assertSee('/journal-seo')->assertDontSee('Route-less SEO node');
});

it('publishes the intentional robots policy', function (): void {
    $this->get('/robots.txt')->assertSuccessful()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
});
