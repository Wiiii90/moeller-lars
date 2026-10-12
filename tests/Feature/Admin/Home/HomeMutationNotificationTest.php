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

it('shows exactly one notification and one mutation for a Home List child publication change', function (): void {
    $settings = homeNotificationSettings([
        ['type' => 'list', 'title' => 'CV', 'published' => true, 'items' => [
            ['title' => 'First', 'published' => true],
        ]],
    ]);
    $page = Livewire::test(HomePresentation::class);
    $beforeNotifications = AdminNotification::query()->count();

    $page->call('publishHomeChild', 0, 'list', 0, false)
        ->assertDispatched('admin-header-notification');

    $component = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0];
    expect($component['items'][0]['published'])->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    $activityCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->call('publishHomeChild', 0, 'list', 0, false);
    expect(AuditEvent::query()->count())->toBe($activityCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});

it('notifies Home child ordering, edits and deletion through the one mutation owner', function (): void {
    $settings = homeNotificationSettings([
        ['type' => 'list', 'title' => 'CV', 'published' => true, 'items' => [
            ['title' => 'First', 'published' => true],
            ['title' => 'Second', 'published' => true],
        ]],
    ]);

    $page = Livewire::test(HomePresentation::class)
        ->call('moveHomeChild', 0, 'list', 1, 'up')
        ->assertDispatched('admin-header-notification');

    $items = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['items'];
    expect($items[0]['title'])->toBe('Second');

    $page->mountAction('editHomeChild', ['index' => 0, 'type' => 'list', 'child' => 0])
        ->set('mountedActions.0.data.title', 'Second revised')
        ->call('callMountedAction')
        ->assertDispatched('admin-header-notification');

    $items = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['items'];
    expect($items[0]['title'])->toBe('Second revised');

    $page->call('unmountAction')
        ->mountAction('deleteHomeChild', ['index' => 0, 'type' => 'list', 'child' => 1])
        ->call('callMountedAction')
        ->assertDispatched('admin-header-notification');

    $items = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['items'];
    expect($items)->toHaveCount(1);
});

it('notifies once when publishing a mixed Home component and child selection', function (): void {
    $settings = homeNotificationSettings([
        ['type' => 'list', 'title' => 'CV', 'published' => true, 'items' => [
            ['title' => 'One', 'published' => true],
        ]],
        ['type' => 'divider', 'variant' => 'thin', 'published' => true],
    ]);

    $beforeNotifications = AdminNotification::query()->count();
    $page = Livewire::test(HomePresentation::class)
        ->set('selectedComponentTargets', ['1:divider'])
        ->set('selectedHomeChildTargets', ['list:0:0'])
        ->call('publishSelectedHomeItems', false)
        ->assertDispatched('admin-header-notification');

    $blocks = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'];
    expect($blocks[0]['items'][0]['published'])->toBeFalse()
        ->and($blocks[1]['published'])->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    $activityCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();
    $page->call('publishSelectedHomeItems', false);
    expect(AuditEvent::query()->count())->toBe($activityCount)
        ->and(AdminNotification::query()->count())->toBe($notificationCount);
});
