<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\CustomPageWorkspace;
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
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $auditCount = \App\Models\AuditEvent::query()->count();

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
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
        ->assertSet('pageTitle', 'Updated title')->assertSee('Updated title');

    $fresh = $section->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->navigation_label)->toBe('Short label')
        ->and($fresh->slug)->toBe('updated-page-slug')
        ->and($fresh->state)->toBe('published')
        ->and((int) $fresh->parent_id)->toBe((int) $parent->id)
        ->and(app(SiteNodeRoute::class)->path($fresh))->toBe('/updated-page-slug')
        ->and(\App\Models\AuditEvent::query()->count())->toBe($auditCount + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(\App\Models\AuditEvent::query()->count())->toBe($auditCount + 2)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('keeps duplicate Custom Page slug errors in the mounted form and rolls back the combined edit', function (): void {
    [$section] = customPageWorkspaceRecord('Original', 'routing-original');
    customPageWorkspaceRecord('Reserved', 'routing-reserved');
    $auditCount = \App\Models\AuditEvent::query()->count();
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('pageSettings')->fillForm(['title' => 'Rejected title', 'slug' => 'routing-reserved'])
        ->callMountedAction()->assertHasFormErrors(['slug'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe('Original')->and($section->fresh()->slug)->toBe('routing-original')
        ->and(\App\Models\AuditEvent::query()->count())->toBe($auditCount)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
    $editor->set('mountedActions.0.data.slug', 'routing-corrected')->callMountedAction()->assertHasNoFormErrors();
    expect($section->fresh()->title)->toBe('Rejected title')->and($section->fresh()->slug)->toBe('routing-corrected');
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

    // A Working redirect must not affect public URLs until an explicit Commit.
    $actor = auth()->user();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    $this->get('/redirect-a')->assertNotFound();

    $this->actingAs($actor, 'web');
    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();

    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    $this->get('/redirect-a')->assertStatus(301)->assertRedirect('/redirect-c');
    $this->get('/redirect-b')->assertStatus(301)->assertRedirect('/redirect-c');
    $this->get('/redirect-c')->assertOk();

    $this->actingAs($actor, 'web');
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


it('hides Custom Page parent settings when global nesting is disabled and rejects a stale parent', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $parent = $sections->createNavigationGroup('Parent');
    [$section] = customPageWorkspaceRecord('No nesting', 'no-nesting');

    \App\Models\PublicContentSetting::general()->update(['navigation_nesting_enabled' => false]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('pageSettings')
        ->assertDontSee('Navigation parent')
        ->set('mountedActions.0.data.title', 'Renamed without nesting')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect($section->fresh()->getAttribute('title'))->toBe('Renamed without nesting')
        ->and($section->fresh()->getAttribute('parent_id'))->toBeNull();

    expect(fn () => $sections->updatePlacement($section->fresh(), 'hidden', (int) $parent->getKey()))
        ->toThrow(ValidationException::class, 'Enable nested pages in Pages settings');
});
