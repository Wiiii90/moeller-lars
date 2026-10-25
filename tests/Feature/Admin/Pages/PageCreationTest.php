<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Models\SiteSection;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Admin\AdminActivityNotificationContext;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/PagesFixtures.php';

it('suggests a public slug from a new page name without replacing manual edits', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $component = Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->set('mountedActions.0.data.name', 'Über meine Arbeiten')
        ->assertSet('mountedActions.0.data.slug', 'uber-meine-arbeiten');

    $component
        ->set('mountedActions.0.data.slug', 'my-own-url')
        ->set('mountedActions.0.data.name', 'Anderer Titel')
        ->assertSet('mountedActions.0.data.slug', 'my-own-url')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect(SiteSection::query()->where('slug', 'my-own-url')->value('title'))->toBe('Anderer Titel');
});


it('keeps generated page slugs within the allowed length and syntax', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $component = Livewire::test(SitePages::class)
        ->mountAction('addPage')
        ->set('mountedActions.0.data.name', str_repeat('Long Page ', 15));

    $slug = $component->get('mountedActions.0.data.slug');
    expect($slug)->toBeString()
        ->and(strlen($slug))->toBeLessThanOrEqual(80)
        ->and($slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
});

it('rejects a duplicate creation slug without creating partial page configuration and allows correction', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    app(SiteSectionEditorialService::class)->createCustomPage('Existing', 'duplicate-creation');
    app(AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count(); $count = SiteSection::query()->count();
    $component = Livewire::test(SitePages::class)->mountAction('addPage')
        ->set('mountedActions.0.data.name', 'New Page')->set('mountedActions.0.data.slug', 'duplicate-creation')
        ->callMountedAction()->assertHasFormErrors(['slug'])->assertNotDispatched('admin-header-notification');
    expect(SiteSection::query()->count())->toBe($count)->and(AuditEvent::query()->count())->toBe($before)
        ->and(AdminNotification::query()->count())->toBe(0);
    $component->set('mountedActions.0.data.slug', 'corrected-creation')->callMountedAction()->assertHasNoFormErrors()->assertSee('New Page');
    expect(SiteSection::query()->where('slug', 'corrected-creation')->exists())->toBeTrue()
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
});


it('creates Pages as drafts by default and publishes each supported type when configured', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    Livewire::test(SitePages::class)->mountAction('addPage')
        ->set('mountedActions.0.data.type', SiteSectionType::CustomPage->value)
        ->set('mountedActions.0.data.name', 'Draft by Default')
        ->set('mountedActions.0.data.slug', 'draft-by-default')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect(SiteSection::query()->where('slug', 'draft-by-default')->value('state'))->toBe('hidden');

    Livewire::test(SitePages::class)->mountAction('pagesSettings')
        ->set('mountedActions.0.data.pages_auto_publish', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    foreach ([
        [SiteSectionType::CustomPage->value, 'Auto Custom', 'auto-custom'],
        [SiteSectionType::Journal->value, 'Auto Journal', 'auto-journal'],
        [SiteSectionType::Gallery->value, 'Auto Gallery', 'auto-gallery'],
        [SiteSectionType::NavigationNode->value, 'Auto Group', null],
    ] as [$type, $name, $slug]) {
        $component = Livewire::test(SitePages::class)->mountAction('addPage')
            ->set('mountedActions.0.data.type', $type)
            ->set('mountedActions.0.data.name', $name);
        if ($slug !== null) {
            $component->set('mountedActions.0.data.slug', $slug);
        }
        if ($type === SiteSectionType::Journal->value) {
            $component->set('mountedActions.0.data.template', JournalTemplate::Blog->value);
        }

        $component->call('callMountedAction')
            ->assertHasNoFormErrors()
            ->assertDispatched('admin-header-notification');

        $section = SiteSection::query()->where('type', $type)->where('title', $name)->firstOrFail();
        expect($section->state)->toBe('published')
            ->and($section->slug)->toBe($slug);
    }

    expect(SiteSection::query()->where('slug', 'draft-by-default')->value('state'))->toBe('hidden');
});
