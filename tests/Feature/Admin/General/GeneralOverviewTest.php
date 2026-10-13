<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\AuditEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GeneralFixtures.php';

it('uses General as the canonical singleton route with no global save action', function (): void {
    $settings = PublicContentSetting::general();

    expect(parse_url(General::getUrl(), PHP_URL_PATH))->toBe('/admin/general')
        ->and(PublicContentSettingResource::getNavigationUrl())->toBe(General::getUrl());

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('General')
        ->assertDontSee('Save changes');

    $legacyUrl = PublicContentSettingResource::getUrl('edit', ['record' => $settings]);
    $this->get($legacyUrl)->assertRedirect(General::getUrl());
});


it('renders the exact six General metrics from persisted settings and audit events', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'public_email' => 'public@example.invalid',
        'show_public_email' => false,
        'contact_recipient_email' => 'delivery@example.invalid',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => false],
        ],
        'default_media_copyright_notice' => 'Default copyright',
        'legal_disclaimer' => 'Global legal text.',
    ]);

    $settings = PublicContentSetting::general();
    $changes = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->where('entity_type', 'public_content_setting')
        ->where('entity_id', (int) $settings->getKey())
        ->where('occurred_at', '>=', now()->subDays(30))
        ->count();

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('Last changed')
        ->assertSee($settings->updated_at->format('j M · H:i'))
        ->assertSee('Changes · 30d')
        ->assertSee((string) $changes)
        ->assertSee('Public email')
        ->assertSee('Configured')
        ->assertSee('Contact delivery')
        ->assertSee('Social profiles')
        ->assertSee('Appearance')
        ->assertSee('Social Media')
        ->assertSee('Legal')
        ->assertDontSee('Save changes');
});
