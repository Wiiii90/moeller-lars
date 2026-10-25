<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Filament\Pages\HomePresentation;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function homeNotificationSettings(array $components): HomePresentationSetting
{
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $settings = HomePresentationSetting::query()->where('site_section_id', $home->getKey())->firstOrFail();
    $configuration = HomePresentationEditorialService::defaults();
    $configuration[HomeTemplate::Custom->value]['components'] = $components;
    $settings->setAttribute('template', HomeTemplate::Custom->value);
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('persists Home List entries in the parent component editor with one notification', function (): void {
    $settings = homeNotificationSettings([[
        'type' => 'list', 'title' => 'CV', 'published' => true, 'items' => [
            ['title' => 'First', 'published' => true],
        ],
    ]]);

    $page = Livewire::test(HomePresentation::class)
        ->mountAction('editListComponent', ['index' => 0, 'type' => 'list'])
        ->assertMountedActionModalSee('List entries');
    $beforeNotifications = AdminNotification::query()->count();
    $page->fillForm([
        'editor_kind' => 'list',
        'list_title' => 'CV',
        'items' => [['title' => 'First revised', 'published' => false]],
    ])->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $component = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0];
    expect($component['items'][0]['title'])->toBe('First revised')
        ->and($component['items'][0]['published'])->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    $activityCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->mountAction('editListComponent', ['index' => 0, 'type' => 'list'])
        ->callMountedAction()
        ->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($activityCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});

it('reorders and removes Home List entries from its Edit dialog', function (): void {
    $settings = homeNotificationSettings([[
        'type' => 'list', 'title' => 'CV', 'published' => true, 'items' => [
            ['title' => 'First', 'published' => true],
            ['title' => 'Second', 'published' => true],
        ],
    ]]);

    Livewire::test(HomePresentation::class)
        ->mountAction('editListComponent', ['index' => 0, 'type' => 'list'])
        ->fillForm([
            'editor_kind' => 'list',
            'list_title' => 'CV',
            'items' => [['title' => 'Second revised', 'published' => true]],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $items = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['items'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Second revised');
});

it('edits Under Construction List entries using the same shared component commit', function (): void {
    $settings = homeNotificationSettings([]);
    $configuration = $settings->getAttribute('configuration');
    $configuration[HomeTemplate::UnderConstruction->value]['components'] = [[
        'type' => 'list', 'title' => 'Roadmap', 'published' => true,
        'items' => [['title' => 'Existing', 'published' => true]],
    ]];
    $settings->setAttribute('template', HomeTemplate::UnderConstruction->value);
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    $page = Livewire::test(HomePresentation::class)
        ->assertSet('template', HomeTemplate::UnderConstruction->value)
        ->assertDontSee('Add list item')
        ->mountAction('editListComponent', ['index' => 0, 'type' => 'list'])
        ->fillForm([
            'editor_kind' => 'list',
            'list_title' => 'Roadmap',
            'items' => [
                ['title' => 'New first', 'published' => true],
                ['title' => 'Existing', 'published' => false],
            ],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $items = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())[HomeTemplate::UnderConstruction->value]['components'][0]['items'];
    expect(array_column($items, 'title'))->toBe(['New first', 'Existing'])
        ->and($items[1]['published'])->toBeFalse();
});

it('notifies once when publishing a mixed Home component and Contact child selection', function (): void {
    $settings = homeNotificationSettings([
        ['type' => 'contact', 'published' => true, 'children' => [
            ['type' => 'public_email', 'published' => true],
        ]],
        ['type' => 'divider', 'variant' => 'thin', 'published' => true],
    ]);

    $beforeNotifications = AdminNotification::query()->count();
    $page = Livewire::test(HomePresentation::class)
        ->set('selectedComponentTargets', ['1:divider'])
        ->set('selectedHomeChildTargets', ['contact:0:public_email'])
        ->call('publishSelectedHomeItems', false)
        ->assertDispatched('admin-header-notification');

    $blocks = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'];
    expect($blocks[0]['children'][0]['published'])->toBeFalse()
        ->and($blocks[1]['published'])->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    $activityCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->call('publishSelectedHomeItems', false);
    expect(AuditEvent::query()->count())->toBe($activityCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});

it('renders a saved Home heading immediately and repeats without audit or feedback', function (): void {
    $settings = homeNotificationSettings([['type' => 'text', 'title' => 'Original heading', 'body' => null]]);
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(HomePresentation::class)->mountAction('editComponent', ['index' => 0, 'type' => 'text'])
        ->set('mountedActions.0.data.title', 'Saved heading')->callMountedAction()->assertHasNoFormErrors()->assertSee('Saved heading');
    expect(app(HomePresentationEditorialService::class)->configuration($settings->fresh())['custom']['components'][0]['title'])->toBe('Saved heading')
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
});

it('rejects a stale Home component editor without overwriting the current record', function (): void {
    $settings = homeNotificationSettings([['type' => 'text', 'title' => 'Original heading', 'body' => null]]);
    $editor = Livewire::test(HomePresentation::class)->mountAction('editComponent', ['index' => 0, 'type' => 'text']);
    app(HomePresentationEditorialService::class)->updateComponent($settings, HomeTemplate::Custom, 0, 'text',
        ['type' => 'text', 'title' => 'Concurrent heading', 'body' => null]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor->set('mountedActions.0.data.title', 'Stale heading')->callMountedAction()
        ->assertHasFormErrors(['editor_kind'])->assertNotDispatched('admin-header-notification');
    expect(app(HomePresentationEditorialService::class)->configuration($settings->fresh())['custom']['components'][0]['title'])->toBe('Concurrent heading')
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});
