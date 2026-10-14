<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\User;
use App\Models\AdminNotification;
use App\Domain\Admin\AdminActivityNotificationContext;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('reports actual row reorder and delete actions once and keeps invalid positions and repeats silent', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/one'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/two'],
    ]]);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->call('sortSocialLink', 1, 0)->assertDispatched('admin-header-notification');
    expect(PublicContentSetting::general()->social_links[0]['platform'])->toBe('facebook')
        ->and(generalSettingsAuditCount())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
    $component->call('sortSocialLink', 0, 0)->call('moveSocialLink', 0, 'up')->call('deleteSocialLink', 999)
        ->assertNotDispatched('admin-header-notification');
    expect(generalSettingsAuditCount())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
    $component->call('deleteSocialLink', 1)->assertDispatched('admin-header-notification');
    expect(PublicContentSetting::general()->social_links)->toHaveCount(1)
        ->and(generalSettingsAuditCount())->toBe($before + 2)->and(AdminNotification::query()->count())->toBe(2);
    $component->call('deleteSocialLink', 1)->assertNotDispatched('admin-header-notification');
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
