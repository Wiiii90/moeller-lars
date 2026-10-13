<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\PublicNavigationService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Domain\Publication\PublicationService;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use App\Routing\SiteNodeRoute;
use Illuminate\Validation\ValidationException;

it('keeps Home singular while configurable page types remain reusable', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $firstPage = $service->createCustomPage('Studio', 'studio-architecture');
    $secondPage = $service->createCustomPage('About', 'about-architecture');
    $blog = $service->createJournal('Notes', 'notes-architecture', JournalTemplate::Blog->value);
    $exhibitions = $service->createJournal('Shows', 'shows-architecture', JournalTemplate::Exhibitions->value);

    expect(SiteSection::query()->where('type', SiteSectionType::Home->value)->count())->toBe(1)
        ->and($firstPage->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($secondPage->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($blog->type)->toBe(SiteSectionType::Journal->value)
        ->and($blog->template)->toBe(JournalTemplate::Blog->value)
        ->and($exhibitions->template)->toBe(JournalTemplate::Exhibitions->value);
});

it('builds navigation from published SiteSections and keeps navigation groups route-less', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $group = $service->createNavigationGroup('Studio');
    $page = $service->createCustomPage('Biography', 'biography-architecture');
    $hidden = $service->createCustomPage('Hidden', 'hidden-architecture');

    $service->updatePlacement($group, 'published', null);
    $service->updatePlacement($page, 'published', (int) $group->getKey());

    $items = app(PublicNavigationService::class)->items();
    $studio = $items->firstWhere('label', 'Studio');

    expect($studio)->not->toBeNull()
        ->and($studio['url'])->toBeNull()
        ->and(collect($studio['children'])->pluck('label')->all())->toContain('Biography')
        ->and($items->pluck('label')->all())->not->toContain('Hidden')
        ->and($group->hasPublicPage())->toBeFalse()
        ->and(app(SiteNodeRoute::class)->path($group))->toBeNull()
        ->and($hidden->state)->toBe('hidden');
});

it('promotes published children of unpublished parents to public navigation roots', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $group = $service->createNavigationGroup('Published Group');
    $child = $service->createJournal('Visible Journal', 'visible-journal', JournalTemplate::Blog->value);
    $draft = $service->createJournal('Unpublished Journal', 'unpublished-journal', JournalTemplate::Blog->value);

    $service->updatePlacement($group, 'published', null);
    $service->updatePlacement($child, 'published', (int) $group->getKey());

    $items = app(PublicNavigationService::class)->items();
    $groupItem = $items->firstWhere('label', 'Published Group');

    expect($groupItem)->not->toBeNull()
        ->and(collect($groupItem['children'])->pluck('label')->all())->toContain('Visible Journal')
        ->and($items->pluck('label')->all())->not->toContain('Unpublished Journal');

    $service->updatePlacement($group, 'hidden', null);
    $items = app(PublicNavigationService::class)->items();

    expect($items->pluck('label')->all())->toContain('Visible Journal')
        ->and($items->pluck('label')->all())->not->toContain('Published Group');

    $service->updatePlacement($child, 'hidden', (int) $group->getKey());
    $items = app(PublicNavigationService::class)->items();
    expect($items->pluck('label')->all())->not->toContain('Visible Journal');
});

it('uses committed SiteSection state as the public availability gate', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Statement', 'statement-architecture');

    $this->get('/statement-architecture')->assertNotFound();

    $service->updatePlacement($page, 'published', null);
    $this->get('/statement-architecture')->assertNotFound();

    app(PublicationService::class)->commit($actor);
    $this->get('/statement-architecture')->assertSuccessful();

    $service->updatePlacement($page, 'hidden', null);
    $this->get('/statement-architecture')->assertSuccessful();

    app(PublicationService::class)->commit($actor);
    $this->get('/statement-architecture')->assertNotFound();
});

it('does not create Activity for an unchanged page placement', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('No-op placement', 'no-op-placement');

    $before = AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', $page->getKey())
        ->count();

    $result = $service->updatePlacement(
        $page,
        (string) $page->getAttribute('state'),
        null,
    );

    expect($result->wasChanged())->toBeFalse()
        ->and(AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', $page->getKey())
            ->count())->toBe($before);
});


it('rejects level three trees without introducing type pairing rules', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $parent = $service->createCustomPage('Parent', 'parent-architecture');
    $child = $service->createNavigationGroup('Child Group');
    $journal = $service->createJournal('Journal', 'journal-architecture', JournalTemplate::Blog->value);

    $service->updatePlacement($child, 'hidden', (int) $parent->getKey());

    expect(fn () => $service->updatePlacement($journal, 'hidden', (int) $child->getKey()))
        ->toThrow(ValidationException::class, 'The parent must be a top-level page.');
});
