<?php

use App\Domain\Content\PublicNavigationService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('keeps Home singular while configurable page types remain reusable', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $firstPage = $service->createCustomPage('Studio', 'studio-architecture');
    $secondPage = $service->createCustomPage('About', 'about-architecture');
    $blog = $service->createJournal('Notes', 'notes-architecture', SiteSection::JOURNAL_TEMPLATE_BLOG);
    $exhibitions = $service->createJournal('Shows', 'shows-architecture', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS);

    expect(SiteSection::query()->where('type', SiteSection::TYPE_HOME)->count())->toBe(1)
        ->and($firstPage->type)->toBe(SiteSection::TYPE_CUSTOM)
        ->and($secondPage->type)->toBe(SiteSection::TYPE_CUSTOM)
        ->and($blog->type)->toBe(SiteSection::TYPE_JOURNAL)
        ->and($blog->template)->toBe(SiteSection::JOURNAL_TEMPLATE_BLOG)
        ->and($exhibitions->template)->toBe(SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS);
});

it('builds navigation from published SiteSections and keeps navigation nodes route-less', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $group = $service->createNavigationGroup('Studio');
    $page = $service->createCustomPage('Biography', 'biography-architecture');
    $hidden = $service->createCustomPage('Hidden', 'hidden-architecture');

    $service->updatePlacement($group, 'published', true, null);
    $service->updatePlacement($page, 'published', true, (int) $group->getKey());

    $items = app(PublicNavigationService::class)->items();
    $studio = $items->firstWhere('label', 'Studio');

    expect($studio)->not->toBeNull()
        ->and($studio['url'])->toBeNull()
        ->and(collect($studio['children'])->pluck('label')->all())->toContain('Biography')
        ->and($items->pluck('label')->all())->not->toContain('Hidden')
        ->and($group->hasPublicPage())->toBeFalse()
        ->and($group->publicPath())->toBeNull()
        ->and($hidden->state)->toBe('hidden');
});

it('uses current SiteSection state as the public availability gate', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Statement', 'statement-architecture');

    $this->get('/statement-architecture')->assertNotFound();

    $service->updatePlacement($page, 'published', false, null);
    $this->get('/statement-architecture')->assertSuccessful();

    $service->updatePlacement($page, 'hidden', false, null);
    $this->get('/statement-architecture')->assertNotFound();
});

it('rejects unsupported nested page trees', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $group = $service->createNavigationGroup('Parent');
    $child = $service->createCustomPage('Child', 'child-architecture');
    $journal = $service->createJournal('Journal', 'journal-architecture', SiteSection::JOURNAL_TEMPLATE_BLOG);

    $service->updatePlacement($child, 'hidden', false, (int) $group->getKey());

    expect(fn () => $service->updatePlacement($journal, 'hidden', false, (int) $child->getKey()))
        ->toThrow(ValidationException::class, 'The parent must be a top-level section that supports submenu entries.');
});
