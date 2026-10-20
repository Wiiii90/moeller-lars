<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Filament\Pages\HomePresentation;
use App\Filament\Pages\CustomPageWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\HomePresentationSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/Pages/CustomPageFixtures.php';

function contactHomeFixture(array $children): HomePresentationSetting
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


it('persists Home Contact settings once', function (): void {
    $settings = contactHomeFixture([[
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

it('defers incomplete Home Contact status', function (): void {
    $settings = contactHomeFixture([[
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


it('persists Custom Page Contact settings once', function (): void {
    [$page, $settings] = customPageWorkspaceRecord('Contact direct save', 'contact-direct-save');
    $settings->update(['blocks' => [[
        'type' => 'contact', 'published' => true, 'title' => 'Contact',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram', 'facebook']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => 'Studio opens soon'],
        ],
    ]]]);
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $page->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->assertSet('mountedActions.0.name', 'editComponent');

    $before = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $editor->set('mountedActions.0.data.contact_social_selected.facebook', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent');
    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['social_links']['social_platforms'])->toBe(['instagram'])
        ->and(AuditEvent::query()->count())->toBe($before + 1);

    $editor->set('mountedActions.0.data.contact_social_selected.instagram', false)
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent');
    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['social_links']['social_platforms'])->toBe([]);

    $editor->set('mountedActions.0.data.contact_form_enabled', false)
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent');
    $editor->set('mountedActions.0.data.contact_email_enabled', false)
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent');
    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['contact_form']['published'])->toBeFalse()
        ->and($children['public_email']['published'])->toBeFalse();

    $editor->set('mountedActions.0.data.contact_form_enabled', true)
        ->assertDispatched('admin-header-notification')
        ->set('mountedActions.0.data.contact_form_state', 'under_construction')
        ->assertDispatched('admin-header-notification')
        ->set('mountedActions.0.data.contact_status_text', 'New status')
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent');
    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['contact_form']['published'])->toBeTrue()
        ->and($children['contact_form']['form_state'])->toBe('under_construction')
        ->and($children['contact_form']['status_text'])->toBe('New status')
        ->and(AuditEvent::query()->count())->toBe($before + 7)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 7);

    // An unchanged value or an extra generic change submission never
    // produces a duplicate receipt or overwrites earlier Contact changes.
    $editor->set('mountedActions.0.data.contact_status_text', 'New status')
        ->assertNotDispatched('admin-header-notification')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 7)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 7);

    $editor->call('unmountAction')->assertSet('mountedActions', []);
    Livewire::test(CustomPageWorkspace::class, ['section' => $page->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->assertSet('mountedActions.0.data.contact_form_state', 'under_construction')
        ->assertSet('mountedActions.0.data.contact_social_selected.facebook', false)
        ->assertSet('mountedActions.0.data.contact_social_selected.instagram', false);
});

it('preserves Custom Page Contact sibling settings', function (): void {
    [$page, $settings] = customPageWorkspaceRecord('Contact heading blur', 'contact-heading-blur');
    $settings->update(['blocks' => [[
        'type' => 'contact', 'published' => true, 'title' => 'Original',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled'],
        ],
    ]]]);
    Livewire::test(CustomPageWorkspace::class, ['section' => $page->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->set('mountedActions.0.data.title', 'Contact us')
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('Contact us');

    $updated = $settings->fresh()->components()[0];
    expect($updated['title'])->toBe('Contact us')
        ->and($updated['children'][1]['social_platforms'])->toBe(['instagram'])
        ->and($updated['children'][2]['form_state'])->toBe('enabled');
});

it('defers incomplete Custom Page Contact status', function (): void {
    [$page, $settings] = customPageWorkspaceRecord('Pending form presentation', 'pending-form-presentation');
    $settings->update(['blocks' => [[
        'type' => 'contact', 'published' => true, 'title' => 'Contact',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
        ],
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $page->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->assertSet('mountedActions.0.data.contact_form_state', 'enabled');
    $audits = AuditEvent::query()->count();

    $editor->set('mountedActions.0.data.contact_form_state', 'under_construction')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertNotDispatched('admin-header-notification');
    expect($settings->fresh()->components()[0]['children'][2]['form_state'])->toBe('enabled')
        ->and(AuditEvent::query()->count())->toBe($audits);

    $editor->set('mountedActions.0.data.contact_status_text', 'Coming soon')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertDispatched('admin-header-notification');

    $children = $settings->fresh()->components()[0]['children'];
    expect($children[2]['form_state'])->toBe('under_construction')
        ->and($children[2]['status_text'])->toBe('Coming soon')
        ->and($children[1]['social_platforms'])->toBe(['instagram'])
        ->and(AuditEvent::query()->count())->toBe($audits + 1);
});
