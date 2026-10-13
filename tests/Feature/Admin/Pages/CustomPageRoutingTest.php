<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Filament\Pages\CustomPageWorkspace;
use App\Http\Controllers\PublicSiteSectionController;
use App\Models\CustomPageSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Models\User;
use App\Routing\SiteNodeRoute;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    config()->set('analytics.matomo.reporting_enabled', false);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @return array{0:SiteSection,1:CustomPageSetting} */

require_once __DIR__.'/CustomPageFixtures.php';

it('updates Custom Page identity placement and flat public path through the canonical page settings action', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $parent = $sections->createNavigationGroup('About');
    [$section] = customPageWorkspaceRecord('Original title', 'original-page-slug');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('pageSettings')
        ->fillForm([
            'title' => 'Updated title',
            'navigation_label' => 'Short label',
            'slug' => 'updated-page-slug',
            'publication_state' => 'published',
            'parent_id' => $parent->id,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('pageTitle', 'Updated title');

    $fresh = $section->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->navigation_label)->toBe('Short label')
        ->and($fresh->slug)->toBe('updated-page-slug')
        ->and($fresh->state)->toBe('published')
        ->and((int) $fresh->parent_id)->toBe((int) $parent->id)
        ->and(app(SiteNodeRoute::class)->path($fresh))->toBe('/updated-page-slug');
});


it('retains old Custom Page slugs as direct 301 redirects collapses chains and protects redirect sources from loops', function (): void {
    $service = app(SiteSectionEditorialService::class);
    [$section] = customPageWorkspaceRecord('Redirect page', 'redirect-a');
    $service->updatePlacement($section, 'published', null);

    $section = $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-b');
    $section = $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-c');

    $owned = Redirect::query()
        ->where('reason', SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON)
        ->orderBy('source_path')
        ->get();

    expect($owned)->toHaveCount(2)
        ->and($owned->pluck('source_path')->all())->toBe(['/redirect-a', '/redirect-b'])
        ->and($owned->pluck('target_path')->all())->toBe(['/redirect-c', '/redirect-c'])
        ->and($owned->pluck('status_code')->all())->toBe([301, 301])
        ->and($owned->contains(fn (Redirect $redirect): bool => $redirect->source_path === $redirect->target_path))->toBeFalse();

    $response = app(PublicSiteSectionController::class)->show('redirect-a');
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toEndWith('/redirect-c');

    expect(fn () => $service->updateCustomPageIdentity($section, 'Redirect page', null, 'redirect-a'))
        ->toThrow(ValidationException::class, 'This public URL slug is reserved or already in use.');

    expect($section->fresh()->slug)->toBe('redirect-c');
});


it('changes hierarchy placement without inventing a hierarchical Custom Page URL or redirect', function (): void {
    $service = app(SiteSectionEditorialService::class);
    $parent = $service->createNavigationGroup('Parent page');
    [$section] = customPageWorkspaceRecord('Flat route page', 'flat-route-page');
    $route = app(SiteNodeRoute::class);
    $beforePath = $route->path($section);
    $beforeRedirects = Redirect::query()->count();

    $section = $service->updatePlacement($section, 'published', $parent->id);

    expect($route->path($section))->toBe('/flat-route-page')
        ->and($route->path($section))->toBe($beforePath)
        ->and((int) $section->parent_id)->toBe((int) $parent->id)
        ->and(Redirect::query()->count())->toBe($beforeRedirects);
});
