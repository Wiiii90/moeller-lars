<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
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
