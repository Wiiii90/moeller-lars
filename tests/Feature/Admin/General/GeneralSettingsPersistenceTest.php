<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Models\AdminNotification;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GeneralFixtures.php';

it('persists changed text once and skips normalized no-op commits', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'before@example.invalid']);
    $auditBefore = generalSettingsAuditCount();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(General::class)
        ->set('data.public_email', 'after@example.invalid')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('after@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $component->call('persistChangedField', 'public_email')
        ->call('persistChangedField', 'public_email');

    expect(generalSettingsAuditCount())->toBe($auditBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(General::class)
        ->call('persistChangedField', 'public_email')
        ->assertNotDispatched('admin-header-notification');

    $component->set('data.background_color', 'aabbcc')
        ->call('persistChangedField', 'background_color');
    $auditAfterColor = generalSettingsAuditCount();
    $notificationsAfterColor = AdminNotification::query()->count();
    $component->set('data.background_color', '#AABBCC')
        ->call('persistChangedField', 'background_color');

    expect(PublicContentSetting::general()->getAttribute('background_color'))->toBe('#AABBCC')
        ->and(generalSettingsAuditCount())->toBe($auditAfterColor)
        ->and(AdminNotification::query()->count())->toBe($notificationsAfterColor);
});


it('saves distinct General layout changes with one audit and one success feedback each', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'public_page_width' => 1080,
        'public_content_padding' => 48,
    ]);
    // Domain fixture setup and Livewire mutations represent separate user requests.
    app(AdminActivityNotificationContext::class)->discard();
    $auditBefore = generalSettingsAuditCount();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(General::class)
        ->assertSet('data.public_page_width', 1080)
        ->assertSet('data.public_content_padding', 48)
        ->set('data.public_page_width', 1160)
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        })
        ->set('data.public_content_padding', 64)
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    $fresh = PublicContentSetting::general();
    expect((int) $fresh->getAttribute('public_page_width'))->toBe(1160)
        ->and((int) $fresh->getAttribute('public_content_padding'))->toBe(64)
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 2);

    $component->call('persistChangedField', 'public_page_width')
        ->call('persistChangedField', 'public_content_padding');

    expect(generalSettingsAuditCount())->toBe($auditBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 2);
});

it('persists contact legal and social edits with success feedback and unchanged-value no-ops', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'contact_recipient_email' => 'before@example.invalid',
        'legal_disclaimer' => 'Before legal text.',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/before'],
        ],
    ]);
    app(AdminActivityNotificationContext::class)->discard();
    $auditBefore = generalSettingsAuditCount();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(General::class)
        ->set('data.contact_recipient_email', 'after@example.invalid')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        })
        ->set('data.legal_disclaimer', 'After legal text.')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        })
        ->call('updateSocialLink', 0, 'url', 'https://example.invalid/after')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    $saved = PublicContentSetting::general();
    expect($saved->getAttribute('contact_recipient_email'))->toBe('after@example.invalid')
        ->and($saved->getAttribute('legal_disclaimer'))->toBe('After legal text.')
        ->and($saved->getAttribute('social_links')[0]['url'])->toBe('https://example.invalid/after')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 3)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 3);

    $component->call('persistChangedField', 'contact_recipient_email')
        ->call('persistChangedField', 'legal_disclaimer')
        ->call('updateSocialLink', 0, 'url', 'https://example.invalid/after');

    expect(generalSettingsAuditCount())->toBe($auditBefore + 3)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 3);
});

it('keeps invalid event persistence visible without replacing persisted data', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'valid@example.invalid']);
    $auditBefore = generalSettingsAuditCount();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(General::class)
        ->set('data.public_email', 'not-an-email')
        ->assertHasErrors(['data.public_email'])
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'danger';
        });

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('valid@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $failure = AdminNotification::query()->latest('id')->firstOrFail();
    expect($failure->getAttribute('status'))->toBe('danger')
        ->and($failure->getAttribute('title'))->toBe('Change not saved');
});


it('loads a persisted legal disclaimer unchanged without writing on mount', function (): void {
    $legalDisclaimer = 'Obwohl ich die Inhalte sowie die hier aufgeführten Verweise regelmäßig pflege, kann ich für diese nicht haften.';
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => $legalDisclaimer,
    ]);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->assertSet('data.legal_disclaimer', $legalDisclaimer);

    expect(PublicContentSetting::general()->getAttribute('legal_disclaimer'))->toBe($legalDisclaimer)
        ->and(generalSettingsAuditCount())->toBe($auditBefore);
});


it('persists media copyright and legal settings through event-driven controls', function (): void {
    $favicon = generalStatusFavicon();
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.favicon_media_asset_id', $favicon->id)
        ->set('data.default_media_copyright_notice', '  Copyright notice  ')
        ->set('data.legal_disclaimer', 'Legal text.');

    $fresh = PublicContentSetting::general();
    expect((int) $fresh->getAttribute('favicon_media_asset_id'))->toBe((int) $favicon->id)
        ->and($fresh->getAttribute('default_media_copyright_notice'))->toBe('Copyright notice')
        ->and($fresh->getAttribute('legal_disclaimer'))->toBe('Legal text.')
        ->and(generalSettingsAuditCount())->toBeGreaterThan($auditBefore);
});
