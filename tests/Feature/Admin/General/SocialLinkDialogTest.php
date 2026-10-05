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
