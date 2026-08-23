<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function generalStatusFavicon(): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/general-favicon.png',
        'original_filename' => 'general-favicon.png',
        'mime_type' => 'image/png',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'general-favicon.png'),
        'state' => 'available',
        'alt_text' => 'Site favicon',
    ]);
}

it('renders the six General status cells from the canonical settings record', function (): void {
    $settings = PublicContentSetting::general();
    $favicon = generalStatusFavicon();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'favicon_media_asset_id' => $favicon->id,
        'public_email' => 'public@example.invalid',
        'show_public_email' => true,
        'contact_recipient_email' => 'delivery@example.invalid',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => true],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook', 'visible' => false],
        ],
        'default_media_copyright_notice' => 'Default copyright',
        'legal_disclaimer' => 'Global legal text.',
    ]);

    $this->get(PublicContentSettingResource::getNavigationUrl())
        ->assertOk()
        ->assertSee('General')
        ->assertSee('Site identity')
        ->assertSee('Favicon set')
        ->assertSee('Public email')
        ->assertSee('Visible')
        ->assertSee('Contact delivery')
        ->assertSee('Configured')
        ->assertSee('Social profiles')
        ->assertSee('Media copyright')
        ->assertSee('Default set')
        ->assertSee('Legal text')
        ->assertSee('Set');
});

it('renders explicit fallback states instead of fake General statistics', function (): void {
    $settings = PublicContentSetting::general();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'favicon_media_asset_id' => null,
        'public_email' => null,
        'show_public_email' => false,
        'contact_recipient_email' => null,
        'social_links' => [],
        'default_media_copyright_notice' => null,
        'legal_disclaimer' => null,
    ]);

    $this->get(PublicContentSettingResource::getNavigationUrl())
        ->assertOk()
        ->assertSee('Missing')
        ->assertSee('Not set')
        ->assertSee('Server fallback')
        ->assertSee('Social profiles')
        ->assertSee('None')
        ->assertSee('Empty');
});

it('saves and reloads General through the single canonical settings record', function (): void {
    $settings = PublicContentSetting::general();
    $id = $settings->getKey();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'public_email' => 'public@example.invalid',
        'show_public_email' => false,
        'contact_recipient_email' => 'delivery@example.invalid',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/profile', 'visible' => true],
        ],
        'default_media_copyright_notice' => 'Default copyright',
        'legal_disclaimer' => 'Legal text',
    ]);

    $reloaded = PublicContentSetting::general();

    expect($reloaded->getKey())->toBe($id)
        ->and($reloaded->getAttribute('scope'))->toBe(PublicContentSetting::SCOPE_GENERAL)
        ->and($reloaded->getAttribute('public_email'))->toBe('public@example.invalid')
        ->and($reloaded->getAttribute('show_public_email'))->toBeFalse()
        ->and($reloaded->getAttribute('contact_recipient_email'))->toBe('delivery@example.invalid')
        ->and($reloaded->getAttribute('social_links'))->toHaveCount(1)
        ->and($reloaded->getAttribute('default_media_copyright_notice'))->toBe('Default copyright')
        ->and($reloaded->getAttribute('legal_disclaimer'))->toBe('Legal text')
        ->and(PublicContentSetting::query()->where('scope', PublicContentSetting::SCOPE_GENERAL)->count())->toBe(1);
});
