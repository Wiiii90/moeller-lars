<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Support\MediaAssetSelect;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GeneralFixtures.php';

it('persists configured social link add update order and delete without a repeater', function (): void {
    $auditBefore = generalSettingsAuditCount();
    $component = Livewire::test(General::class)
        ->call('addSocialLink')
        ->call('updateSocialLink', 0, 'url', 'https://example.invalid/profile')
        ->call('updateSocialLink', 0, 'platform', 'instagram');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('social_links')[0])->toMatchArray([
        'platform' => 'instagram',
        'url' => 'https://example.invalid/profile',
    ])
        ->and($fresh->getAttribute('social_links')[0])->not->toHaveKey('visible');

    $component->call('deleteSocialLink', 0);
    expect(PublicContentSetting::general()->getAttribute('social_links'))->toBe([])
        ->and(generalSettingsAuditCount())->toBeGreaterThan($auditBefore);
});


it('drag-reorders configured social links by array position without filter state', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/alpha'],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/beta'],
        ],
    ]);

    $component = Livewire::test(General::class)
        ->call('sortSocialLink', 1, 0);

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('social_links')[0]['platform'])->toBe('facebook')
        ->and($fresh->getAttribute('social_links')[1]['platform'])->toBe('instagram')
        ->and($component->instance()->socialRows())->toHaveCount(2);
});
