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

it('edits a social profile and moves it to the chosen position in one dialog submission', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook'],
        ],
    ]);

    Livewire::test(General::class)
        ->mountAction('editSocialLink', ['index' => 1])
        ->fillForm([
            'platform' => 'facebook',
            'url' => 'https://example.invalid/facebook-updated',
            'position' => 1,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $links = PublicContentSetting::general()->getAttribute('social_links');
    expect($links[0])->toMatchArray([
        'platform' => 'facebook',
        'url' => 'https://example.invalid/facebook-updated',
    ])
        ->and($links[1]['platform'])->toBe('instagram');
});
