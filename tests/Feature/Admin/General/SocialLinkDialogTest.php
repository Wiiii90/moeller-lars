<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\User;
use App\Models\AdminNotification;
use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Admin\AdminAuditService;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/GeneralFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('keeps duplicate platform errors on the mounted Add or Edit form', function (bool $edit): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/one'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/two'],
    ]]);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->mountAction($edit ? 'editSocialLink' : 'addSocialLink', $edit ? ['index' => 1] : [])
        ->fillForm(['platform' => 'instagram', 'url' => 'https://example.invalid/duplicate', 'position' => 1])
        ->callMountedAction()->assertHasFormErrors(['platform'])->assertNotDispatched('admin-header-notification');
    expect(PublicContentSetting::query()->findOrFail(1)->social_links)->toHaveCount(2)
        ->and(generalSettingsAuditCount())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
    $component->set('mountedActions.0.data.platform', 'bluesky')->callMountedAction()->assertHasNoFormErrors();
    expect(generalSettingsAuditCount())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
})->with(['Add' => false, 'Edit' => true]);

it('rejects non HTTP profile URLs at the domain boundary with a visible field error', function (): void {
    $before = generalSettingsAuditCount();
    Livewire::test(General::class)->mountAction('addSocialLink')
        ->fillForm(['platform' => 'instagram', 'url' => 'ftp://example.invalid/profile', 'position' => 1])
        ->callMountedAction()->assertHasFormErrors(['url'])->assertNotDispatched('admin-header-notification');
    expect(PublicContentSetting::query()->findOrFail(1)->social_links ?? [])->toBe([])
        ->and(generalSettingsAuditCount())->toBe($before);
});

it('rolls back a failed social save and allows correction in the same mounted dialog', function (): void {
    $audit = Mockery::mock(app(AdminAuditService::class));
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(AdminAuditService::class, $audit);
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->mountAction('addSocialLink')
        ->fillForm(['platform' => 'instagram', 'url' => 'https://example.invalid/profile', 'position' => 1])
        ->callMountedAction()->assertHasFormErrors(['url'])->assertNotDispatched('admin-header-notification');
    expect(PublicContentSetting::query()->findOrFail(1)->social_links ?? [])->toBe([])
        ->and(generalSettingsAuditCount())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
    app()->forgetInstance(AdminAuditService::class);
    app()->forgetInstance(AdminSettingsService::class);
    $component->callMountedAction()->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
});

it('cancels Add without persisting or creating feedback', function (): void {
    $before = generalSettingsAuditCount();
    Livewire::test(General::class)->mountAction('addSocialLink')
        ->fillForm(['platform' => 'instagram', 'url' => 'https://example.invalid/profile', 'position' => 1])->unmountAction();
    expect(PublicContentSetting::general()->social_links ?? [])->toBe([])
        ->and(generalSettingsAuditCount())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('saves combined profile edits once and reopens the persisted values', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/one'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/two'],
    ]]);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->mountAction('editSocialLink', ['index' => 0])
        ->fillForm(['platform' => 'bluesky', 'url' => 'https://example.invalid/new', 'position' => 2])->callMountedAction()
        ->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
    expect(PublicContentSetting::general()->social_links[1])->toBe(['platform' => 'bluesky', 'url' => 'https://example.invalid/new'])
        ->and(generalSettingsAuditCount())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
    $component->callMountedAction()->assertNotDispatched('admin-header-notification');
    Livewire::test(General::class)->mountAction('editSocialLink', ['index' => 1])
        ->assertActionDataSet(['platform' => 'bluesky', 'url' => 'https://example.invalid/new', 'position' => 2]);
});

it('does not restore profiles removed after a dialog was opened', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/one'],
    ]]);
    $component = Livewire::test(General::class)->mountAction('editSocialLink', ['index' => 0]);
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['social_links' => []]);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component->set('mountedActions.0.data.url', 'https://example.invalid/stale')->callMountedAction()
        ->assertHasFormErrors(['platform'])->assertNotDispatched('admin-header-notification');
    expect(PublicContentSetting::query()->findOrFail(1)->social_links)->toBe([])
        ->and(generalSettingsAuditCount())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('adds a social profile only when the dialog is submitted and inserts it at the chosen position', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
        ],
    ]);

    $component = Livewire::test(General::class)
        ->mountAction('addSocialLink')
        ->fillForm([
            'platform' => 'facebook',
            'url' => 'https://example.invalid/facebook',
            'position' => 1,
        ]);

    expect(PublicContentSetting::general()->getAttribute('social_links'))->toHaveCount(1);

    $component
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $links = PublicContentSetting::general()->getAttribute('social_links');
    expect($links)->toHaveCount(2)
        ->and($links[0])->toMatchArray([
            'platform' => 'facebook',
            'url' => 'https://example.invalid/facebook',
        ])
        ->and($links[1]['platform'])->toBe('instagram');
});


it('autosaves each Edit profile change while the dialog stays mounted across reordering', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook'],
            ['platform' => 'youtube', 'url' => 'https://example.invalid/youtube'],
        ],
    ]);

    $component = Livewire::test(General::class)
        ->mountAction('editSocialLink', ['index' => 0])
        ->assertSet('mountedActions.0.name', 'editSocialLink');

    $component->set('mountedActions.0.data.position', 3)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editSocialLink');

    $links = PublicContentSetting::general()->getAttribute('social_links');
    expect(array_column($links, 'platform'))->toBe(['facebook', 'youtube', 'instagram']);

    $component->set('mountedActions.0.data.url', 'https://example.invalid/instagram-new')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editSocialLink');

    $links = PublicContentSetting::general()->getAttribute('social_links');
    expect($links[0]['url'])->toBe('https://example.invalid/facebook')
        ->and($links[2]['url'])->toBe('https://example.invalid/instagram-new');

    $component->set('mountedActions.0.data.platform', 'bluesky')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $component->set('mountedActions.0.data.url', 'https://example.invalid/bluesky')
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $links = PublicContentSetting::general()->getAttribute('social_links');
    expect($links[2])->toMatchArray([
        'platform' => 'bluesky',
        'url' => 'https://example.invalid/bluesky',
    ]);

    $component->call('unmountAction')->assertSet('mountedActions', []);

    expect(PublicContentSetting::general()->getAttribute('social_links')[2]['platform'])->toBe('bluesky');
});

it('does not save an empty required Edit profile URL and does not discard the persisted value', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [['platform' => 'instagram', 'url' => 'https://example.invalid/valid']],
    ]);

    Livewire::test(General::class)
        ->mountAction('editSocialLink', ['index' => 0])
        ->set('mountedActions.0.data.url', '')
        ->call('callMountedAction')
        ->assertHasFormErrors(['url'])
        ->assertSet('mountedActions.0.name', 'editSocialLink')
        ->call('unmountAction');

    expect(PublicContentSetting::general()->getAttribute('social_links')[0]['url'])
        ->toBe('https://example.invalid/valid');
});
