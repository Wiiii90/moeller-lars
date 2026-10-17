<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Filament\Pages\HomePresentation;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\HomePresentationSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function homeContactAutosaveFixture(array $children): HomePresentationSetting
{
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $settings = HomePresentationSetting::query()->where('site_section_id', $home->getKey())->firstOrFail();
    $configuration = HomePresentationEditorialService::defaults();
    $configuration[HomeTemplate::Custom->value]['components'] = [[
        'type' => 'contact', 'published' => true, 'title' => 'Contact', 'children' => $children,
    ]];
    $settings->setAttribute('template', HomeTemplate::Custom->value);
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    PublicContentSetting::general()->update(['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook'],
    ]]);
});

it('updates Home Social selections immediately without closing the Edit action', function (): void {
    $settings = homeContactAutosaveFixture([[
        'type' => 'social_links', 'published' => true,
        'social_platforms' => ['instagram', 'facebook'],
    ]]);
    $before = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();

    $editor = Livewire::test(HomePresentation::class)
        ->mountAction('editHomeChild', ['index' => 0, 'type' => 'contact', 'child' => 'social_links'])
        ->assertSet('mountedActions.0.name', 'editHomeChild');
    $editor->set('mountedActions.0.data.social_platforms', ['instagram'])
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editHomeChild');

    $children = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['children'];
    expect($children[0]['social_platforms'])->toBe(['instagram'])
        ->and(AuditEvent::query()->count())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe($notifications + 1);

    $editor->set('mountedActions.0.data.social_platforms', [])
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editHomeChild');
    $children = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['children'];
    expect($children[0]['social_platforms'])->toBe([]);

    $count = AuditEvent::query()->count();
    $editor->set('mountedActions.0.data.social_platforms', [])
        ->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($count);
});

it('keeps an incomplete Home form-presentation draft open until its status text is present', function (): void {
    $settings = homeContactAutosaveFixture([[
        'type' => 'contact_form', 'published' => true,
        'form_state' => 'enabled', 'status_text' => null,
    ]]);

    $editor = Livewire::test(HomePresentation::class)
        ->mountAction('editHomeChild', ['index' => 0, 'type' => 'contact', 'child' => 'contact_form'])
        ->assertSet('mountedActions.0.data.form_state', 'enabled');
    $before = AuditEvent::query()->count();

    $editor->set('mountedActions.0.data.form_state', 'under_construction')
        ->assertSet('mountedActions.0.name', 'editHomeChild')
        ->assertNotDispatched('admin-header-notification');
    $children = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['children'];
    expect($children[0]['form_state'])->toBe('enabled')
        ->and(AuditEvent::query()->count())->toBe($before);

    $editor->set('mountedActions.0.data.status_text', 'Opening soon')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editHomeChild');
    $children = app(HomePresentationEditorialService::class)
        ->configuration($settings->fresh())['custom']['components'][0]['children'];
    expect($children[0]['form_state'])->toBe('under_construction')
        ->and($children[0]['status_text'])->toBe('Opening soon')
        ->and(AuditEvent::query()->count())->toBe($before + 1);
});
