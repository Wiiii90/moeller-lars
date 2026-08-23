<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Support\MediaAssetSelect;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
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
        ->assertSee('Set')
        ->assertDontSee('Save changes');
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
        ->assertSee('Empty')
        ->assertDontSee('Save changes');
});

it('autosaves General fields into the single canonical record without a save action', function (): void {
    $settings = PublicContentSetting::general();
    $id = $settings->getKey();
    $favicon = generalStatusFavicon();

    $component = Livewire::test(EditPublicContentSetting::class, ['record' => $id])
        ->assertOk()
        ->assertDontSee('Save changes');

    $component->set('data.public_email', 'autosave@example.invalid');
    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('autosave@example.invalid');

    $component->set('data.show_public_email', false);
    expect(PublicContentSetting::general()->getAttribute('show_public_email'))->toBeFalse();

    $component->set('data.favicon_media_asset_id', $favicon->id);
    expect((int) PublicContentSetting::general()->getAttribute('favicon_media_asset_id'))->toBe((int) $favicon->id);

    $component->set('data.contact_recipient_email', 'recipient@example.invalid');
    $component->set('data.default_media_copyright_notice', 'Autosaved default copyright');
    $component->set('data.legal_disclaimer', 'Autosaved legal text');

    $reloaded = PublicContentSetting::general();
    expect($reloaded->getKey())->toBe($id)
        ->and($reloaded->getAttribute('scope'))->toBe(PublicContentSetting::SCOPE_GENERAL)
        ->and($reloaded->getAttribute('contact_recipient_email'))->toBe('recipient@example.invalid')
        ->and($reloaded->getAttribute('default_media_copyright_notice'))->toBe('Autosaved default copyright')
        ->and($reloaded->getAttribute('legal_disclaimer'))->toBe('Autosaved legal text')
        ->and(PublicContentSetting::query()->where('scope', PublicContentSetting::SCOPE_GENERAL)->count())->toBe(1);
});

it('keeps invalid autosave input visible as a field error without replacing persisted data', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, ['public_email' => 'valid@example.invalid']);

    Livewire::test(EditPublicContentSetting::class, ['record' => $settings->getKey()])
        ->set('data.public_email', 'not-an-email')
        ->call('autosaveField', 'public_email')
        ->assertHasErrors(['data.public_email']);

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('valid@example.invalid');
});

it('searches favicon choices by filename and keeps the picker image-only and available-only', function (): void {
    $image = MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.png',
        'original_filename' => 'picker-match.png',
        'mime_type' => 'image/png',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'picker-match.png'),
        'state' => 'available',
        'alt_text' => 'Picker image',
    ]);
    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.mp4',
        'original_filename' => 'picker-match.mp4',
        'mime_type' => 'video/mp4',
        'byte_size' => 5,
        'sha256' => hash('sha256', 'picker-match.mp4'),
        'state' => 'available',
    ]);
    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match-hidden.png',
        'original_filename' => 'picker-match-hidden.png',
        'mime_type' => 'image/png',
        'byte_size' => 6,
        'sha256' => hash('sha256', 'picker-match-hidden.png'),
        'state' => 'quarantined',
    ]);

    $results = MediaAssetSelect::searchOptions('picker-match', imagesOnly: true);

    expect(array_keys($results))->toBe([(int) $image->id])
        ->and($results[(int) $image->id])->toContain('picker-match.png');
});
