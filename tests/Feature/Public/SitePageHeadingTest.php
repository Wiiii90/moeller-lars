<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SitePageHeading;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Publication\PublicationService;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('defaults new Journals to a disabled content heading', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $service = app(SiteSectionEditorialService::class);
    $section = $service->createJournal('EXHIBITIONS', 'heading-test-exhibitions', JournalTemplate::Exhibitions->value);
    $service->updatePlacement($section, 'published', null);

    expect(SitePageHeading::formState($section->fresh()))->toMatchArray([
        'content_heading_enabled' => false,
        'content_heading_override' => null,
        'content_heading_alignment' => 'left',
        'content_heading_divider' => 'none',
    ]);

    $this->get('/heading-test-exhibitions')->assertNotFound();
    app(PublicationService::class)->commit($actor, 'Publish heading fixture');
    $this->get('/heading-test-exhibitions')->assertOk();
});

it('supports inherited and overridden page headings without altering navigation identity', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $service = app(SiteSectionEditorialService::class);
    $section = $service->createJournal('My exhibitions', 'heading-test-custom', JournalTemplate::Exhibitions->value);
    $section = $service->updatePlacement($section, 'published', null);

    $service->updatePageHeading($section, [
        'content_heading_enabled' => true,
        'content_heading_alignment' => 'center',
        'content_heading_divider' => 'dotted',
    ]);
    $section = $section->fresh();

    expect(SitePageHeading::text($section))->toBe('My exhibitions');
    $this->get('/heading-test-custom')->assertNotFound();
    app(PublicationService::class)->commit($actor, 'Publish inherited heading');
    $this->get('/heading-test-custom')
        ->assertOk()
        ->assertSee('My exhibitions');

    $service->updatePageHeading($section, ['content_heading_override' => 'Current programme']);
    $section = $section->fresh();

    expect(SitePageHeading::text($section))->toBe('Current programme')
        ->and($section->getAttribute('title'))->toBe('My exhibitions')
        ->and($section->getAttribute('navigation_label'))->toBe('My exhibitions');

    $this->get('/heading-test-custom')->assertOk()->assertSee('My exhibitions')->assertDontSee('Current programme');
    $this->get('/preview/heading-test-custom')->assertOk()->assertSee('Current programme');
    app(PublicationService::class)->commit($actor, 'Publish overridden heading');
    $this->get('/heading-test-custom')
        ->assertOk()
        ->assertSee('Current programme');

    $service->updatePageHeading($section, ['content_heading_enabled' => false]);
    $this->get('/heading-test-custom')->assertOk()->assertSee('Current programme');
    app(PublicationService::class)->commit($actor, 'Publish disabled heading');
    $this->get('/heading-test-custom')
        ->assertOk()
        ->assertDontSee('Current programme');
});

it('validates heading controls and uses one schema for Pages and Home', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $home = app(HomePresentationResolver::class)->settings()->siteSection;

    $service->updatePageHeading($home, [
        'content_heading_enabled' => true,
        'content_heading_override' => 'Studio',
        'content_heading_alignment' => 'right',
        'content_heading_divider' => 'strong',
    ]);
    $home = SiteSection::query()->findOrFail($home->getKey());
    expect(SitePageHeading::formState($home))->toMatchArray([
        'content_heading_enabled' => true,
        'content_heading_override' => 'Studio',
        'content_heading_alignment' => 'right',
        'content_heading_divider' => 'strong',
    ]);

    expect(fn () => $service->updatePageHeading($home, [
        'content_heading_alignment' => 'justify',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $service->updatePageHeading($home, [
        'content_heading_divider' => 'double',
    ]))->toThrow(ValidationException::class);
});
