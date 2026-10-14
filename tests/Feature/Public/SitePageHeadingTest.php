<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SitePageHeading;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('starts with no automatic Journal content heading or leading separator', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $service = app(SiteSectionEditorialService::class);
    $section = $service->createJournal('EXHIBITIONS', 'heading-test-exhibitions', JournalTemplate::Exhibitions->value);
    $service->updatePlacement($section, 'published', null);

    expect(SitePageHeading::formState($section->fresh()))->toMatchArray([
        'content_heading_enabled' => false,
        'content_heading_override' => null,
        'content_heading_alignment' => 'left',
        'content_heading_divider' => 'none',
    ]);

    $this->get('/heading-test-exhibitions')
        ->assertOk()
        ->assertDontSee('public-page-heading--left', false)
        ->assertDontSee('exhibitions-heading', false);

    $exhibitionView = file_get_contents(resource_path('views/pages/exhibitions.blade.php'));
    expect($exhibitionView)->toContain('<x-public.page-heading :section="$section" />')
        ->not->toContain('<h2 id="exhibitions-heading"');
});

it('supports inherited and overridden page headings without altering navigation identity', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
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
    $this->get('/heading-test-custom')
        ->assertOk()
        ->assertSee('public-page-heading--center', false)
        ->assertSee('custom-page__divider--dotted', false)
        ->assertSee('My exhibitions');

    $service->updatePageHeading($section, ['content_heading_override' => 'Current programme']);
    $section = $section->fresh();

    expect(SitePageHeading::text($section))->toBe('Current programme')
        ->and($section->getAttribute('title'))->toBe('My exhibitions')
        ->and($section->getAttribute('navigation_label'))->toBe('My exhibitions');

    $this->get('/heading-test-custom')
        ->assertOk()
        ->assertSee('Current programme')
        ->assertSee('public-page-heading--center', false);

    $service->updatePageHeading($section, ['content_heading_enabled' => false]);
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

    expect(file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php')))
        ->toContain('PageHeadingFields::schema(');
    expect(file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePageEditDialog.php')))
        ->toContain('PageHeadingFields::schema(');
    expect(file_get_contents(resource_path('views/pages/home.blade.php')))
        ->toContain('<x-public.page-heading :section="$pageSection" />');
    expect(file_get_contents(resource_path('views/pages/artworks/index.blade.php')))
        ->toContain('<x-public.page-heading :section="$section" />');
});

it('exposes one heading editor for Custom Page creation and a stable Pages edit schema', function (): void {
    $create = file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePageCreateDialog.php'));
    $edit = file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePageEditDialog.php'));
    $shared = file_get_contents(app_path('Filament/Support/PageHeadingFields.php'));

    expect($create)->toContain('PageHeadingFields::schema()')
        ->toContain('updatePageHeading($fresh, SitePageHeading::validated(')
        ->and($edit)->toContain('->dialogSection($arguments)->hasPublicPage()')
        ->toContain("'public_path' => '/'")
        ->toContain("SiteSectionIdentityService::class")
        ->and($shared)->toContain("Toggle::make('content_heading_enabled')")
        ->toContain("Show page heading");
});
