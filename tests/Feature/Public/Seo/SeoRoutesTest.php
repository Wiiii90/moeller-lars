<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\User;

it('exposes the canonical sitemap and keeps administration out of robots discovery', function (): void {
    config(['app.url' => 'https://artist.example.test']);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Sitemap: https://artist.example.test/sitemap.xml', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('https://artist.example.test', false)
        ->assertDontSee('/admin', false)
        ->assertDontSee('/preview', false);
});

it('lists only committed published Journal routes, not Working drafts', function (): void {
    config(['app.url' => 'https://artist.example.test']);
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $editor = app(SiteSectionEditorialService::class);
    $published = $editor->createJournal('Open Journal', 'published-sitemap-journal', JournalTemplate::Blog->value);
    $editor->updatePlacement($published, 'published', null);
    $editor->createJournal('Unpublished Journal', 'draft-sitemap-journal', JournalTemplate::Blog->value);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('/published-sitemap-journal', false)
        ->assertDontSee('/draft-sitemap-journal', false);

    expect(app(PublicationService::class)->commit($actor))->not->toBeNull();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://artist.example.test/published-sitemap-journal', false)
        ->assertDontSee('/draft-sitemap-journal', false);
});
