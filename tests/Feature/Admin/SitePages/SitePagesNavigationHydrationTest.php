<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\PublicNavigationService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Filament\Support\SiteNavigation;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function navigationLabels(): array
{
    return array_map(
        static fn ($item): string => $item->getLabel(),
        app(SiteNavigation::class)->items(),
    );
}

it('refreshes the existing Sidebar and reorders its data after Pages moves', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $editor = app(SiteSectionEditorialService::class);
    $first = $editor->createCustomPage('First Sidebar Page', 'first-sidebar-page');
    $second = $editor->createCustomPage('Second Sidebar Page', 'second-sidebar-page');
    $editor->updatePlacement($first, 'published', true, null);
    $editor->updatePlacement($second, 'published', true, null);

    $before = navigationLabels();
    expect($before)->toContain('First Sidebar Page', 'Second Sidebar Page');
    expect(array_search('First Sidebar Page', $before, true))
        ->toBeLessThan(array_search('Second Sidebar Page', $before, true));

    Livewire::test(SitePages::class)
        ->call('moveSection', (int) $second->getKey(), 'up')
        ->assertDispatched('refresh-sidebar');

    $after = navigationLabels();
    expect(array_search('Second Sidebar Page', $after, true))
        ->toBeLessThan(array_search('First Sidebar Page', $after, true));

    $public = app(PublicNavigationService::class)->items()->pluck('label')->all();
    expect($public)->toContain('First Sidebar Page', 'Second Sidebar Page');
    expect(array_search('Second Sidebar Page', $public, true))
        ->toBeLessThan(array_search('First Sidebar Page', $public, true));
});

it('keeps drafts and public-navigation-hidden pages editable in the Sidebar while the public menu follows visibility', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $editor = app(SiteSectionEditorialService::class);
    $page = $editor->createCustomPage('Navigation Visibility Target', 'navigation-visibility-target');
    $editor->updatePlacement($page, 'published', true, null);
    expect(navigationLabels())->toContain('Navigation Visibility Target');

    $component = Livewire::test(SitePages::class);
    $component->call('toggleSectionNavigation', (int) $page->getKey())
        ->assertDispatched('refresh-sidebar');

    expect(navigationLabels())->toContain('Navigation Visibility Target')
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())
        ->not->toContain('Navigation Visibility Target')
        ->and(SiteSection::query()->whereKey($page->getKey())->exists())->toBeTrue();

    $component->call('toggleSectionNavigation', (int) $page->getKey())
        ->assertDispatched('refresh-sidebar');
    expect(navigationLabels())->toContain('Navigation Visibility Target');

    $component->call('toggleSectionState', (int) $page->getKey())
        ->assertDispatched('refresh-sidebar');
    expect(navigationLabels())->toContain('Navigation Visibility Target')
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())
        ->not->toContain('Navigation Visibility Target')
        ->and($page->fresh()->state)->toBe('hidden');
});

it('hides Home in both navigation projections immediately on Skip Home, then restores it', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $editor = app(SiteSectionEditorialService::class);
    $destination = $editor->createCustomPage('Skip Navigation Destination', 'skip-navigation-destination');
    $editor->updatePlacement($destination, 'published', true, null);

    $home = SiteSection::query()->where('type', SiteSectionType::Home->value)->firstOrFail();
    $homeLabel = (string) ($home->navigation_label ?: $home->title);
    $routing = app(HomeRoutingSettingsService::class);
    $settings = app(HomePresentationResolver::class)->settings();
    expect($routing->enabled($settings))->toBeFalse();

    $component = Livewire::test(SitePages::class)
        ->call('toggleSkipHome')
        ->assertDispatched('refresh-sidebar');

    expect($routing->enabled($settings->fresh()))->toBeTrue()
        ->and(navigationLabels())->not->toContain($homeLabel)
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->not->toContain($homeLabel);

    $component->call('toggleSkipHome');
    expect($routing->enabled($settings->fresh()))->toBeFalse()
        ->and(navigationLabels())->toContain($homeLabel)
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->toContain($homeLabel);
});

it('does not refresh the Sidebar for filter changes or an unchanged page mutation', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $page = app(SiteSectionEditorialService::class)->createCustomPage('No Sidebar Mutation', 'no-sidebar-mutation');
    $component = Livewire::test(SitePages::class);
    $component->set('search', 'not-found')
        ->assertNotDispatched('refresh-sidebar');

    $component->set('search', '');
    $component->call('convertSectionType', (int) $page->getKey(), SiteSectionType::CustomPage->value)
        ->assertNotDispatched('refresh-sidebar');
});


it('adds unpublished Pages via the real dialog and refreshes the editorial Sidebar immediately', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $cases = [
        ['type' => SiteSectionType::CustomPage->value, 'name' => 'Fresh Draft Page', 'slug' => 'fresh-draft-page'],
        ['type' => SiteSectionType::Journal->value, 'name' => 'Fresh Draft Journal', 'slug' => 'fresh-draft-journal', 'template' => 'blog'],
        ['type' => SiteSectionType::Gallery->value, 'name' => 'Fresh Draft Gallery', 'slug' => 'fresh-draft-gallery'],
        ['type' => SiteSectionType::NavigationNode->value, 'name' => 'Fresh Draft Group'],
    ];

    foreach ($cases as $data) {
        $label = $data['name'];
        expect(navigationLabels())->not->toContain($label);

        Livewire::test(SitePages::class)
            ->mountAction('addPage')
            ->fillForm($data)
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertDispatched('refresh-sidebar');

        $created = SiteSection::query()->where('title', $label)->firstOrFail();
        expect($created->getAttribute('state'))->toBe('hidden')
            ->and(navigationLabels())->toContain($label)
            ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->not->toContain($label);
    }
});

it('does not add a Sidebar entry or fire refresh when Add page validation fails', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->fillForm([
            'type' => SiteSectionType::CustomPage->value,
            'name' => '',
            'slug' => 'invalid-empty-name',
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotDispatched('refresh-sidebar');

    expect(SiteSection::query()->where('slug', 'invalid-empty-name')->exists())->toBeFalse();
});

it('keeps the new sidebar draft after opening Pages again and updates its published state without duplicating it', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $editor = app(SiteSectionEditorialService::class);
    $draft = $editor->createCustomPage('Live Draft', 'live-draft');

    expect(navigationLabels())->toContain('Live Draft');
    expect(app(PublicNavigationService::class)->items()->pluck('label')->all())->not->toContain('Live Draft');

    Livewire::test(SitePages::class)
        ->call('toggleSectionState', (int) $draft->getKey())
        ->assertDispatched('refresh-sidebar');

    expect(navigationLabels())->toContain('Live Draft')
        ->and(array_count_values(navigationLabels())['Live Draft'])->toBe(1)
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->not->toContain('Live Draft');

    Livewire::test(SitePages::class)
        ->call('toggleSectionNavigation', (int) $draft->getKey())
        ->assertDispatched('refresh-sidebar');

    expect(navigationLabels())->toContain('Live Draft')
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->toContain('Live Draft');
});
