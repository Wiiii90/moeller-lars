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

it('persists changed text once and skips normalized no-op commits', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'before@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    $component = Livewire::test(General::class)
        ->set('data.public_email', 'after@example.invalid');

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('after@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 1);

    $component->call('persistChangedField', 'public_email')
        ->call('persistChangedField', 'public_email');

    expect(generalSettingsAuditCount())->toBe($auditBefore + 1);

    $component->set('data.background_color', 'aabbcc')
        ->call('persistChangedField', 'background_color');
    $auditAfterColor = generalSettingsAuditCount();
    $component->set('data.background_color', '#AABBCC')
        ->call('persistChangedField', 'background_color');

    expect(PublicContentSetting::general()->getAttribute('background_color'))->toBe('#AABBCC')
        ->and(generalSettingsAuditCount())->toBe($auditAfterColor);
});


it('delivers ticker feedback for General layout changes', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'public_page_width' => 1080,
        'public_content_padding' => 48,
    ]);

    Livewire::test(General::class)
        ->assertSet('data.public_page_width', 1080)
        ->assertSet('data.public_content_padding', 48)
        ->set('data.public_page_width', 1160)
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && ($message['body'] ?? null) === 'Public page width updated.'
                && ($message['status'] ?? null) === 'success';
        })
        ->set('data.public_content_padding', 64)
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && ($message['body'] ?? null) === 'Public content padding updated.'
                && ($message['status'] ?? null) === 'success';
        });

    $fresh = PublicContentSetting::general();

    expect((int) $fresh->getAttribute('public_page_width'))->toBe(1160)
        ->and((int) $fresh->getAttribute('public_content_padding'))->toBe(64);
});


it('delivers ticker feedback for General contact legal and social mutations', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'contact_recipient_email' => 'before@example.invalid',
        'legal_disclaimer' => 'Before legal text.',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/before'],
        ],
    ]);

    Livewire::test(General::class)
        ->set('data.contact_recipient_email', 'after@example.invalid')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && ($message['body'] ?? null) === 'Contact form recipient updated.'
                && ($message['status'] ?? null) === 'success';
        })
        ->set('data.legal_disclaimer', 'After legal text.')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && ($message['body'] ?? null) === 'Legal disclaimer updated.'
                && ($message['status'] ?? null) === 'success';
        })
        ->call('updateSocialLink', 0, 'url', 'https://example.invalid/after')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && ($message['body'] ?? null) === 'Social links updated.'
                && ($message['status'] ?? null) === 'success';
        });
});


it('keeps invalid event persistence visible without replacing persisted data', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'valid@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.public_email', 'not-an-email')
        ->assertHasErrors(['data.public_email']);

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('valid@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore);
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
