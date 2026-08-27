<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Support\MediaAssetSelect;
use App\Models\AuditEvent;
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

function generalSettingsAuditCount(): int
{
    return AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->where('entity_type', 'public_content_setting')
        ->count();
}

it('uses General as the canonical singleton route and redirects the legacy record edit route', function (): void {
    $settings = PublicContentSetting::general();

    expect(parse_url(General::getUrl(), PHP_URL_PATH))->toBe('/admin/general')
        ->and(PublicContentSettingResource::getNavigationUrl())->toBe(General::getUrl());

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('General')
        ->assertDontSee('Public Content Settings')
        ->assertDontSee('Edit Public Content Setting')
        ->assertDontSee('Save changes');

    $legacyUrl = PublicContentSettingResource::getUrl('edit', ['record' => $settings]);

    $this->get($legacyUrl)->assertRedirect(General::getUrl());
});

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

    $this->get(General::getUrl())
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

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('Missing')
        ->assertSee('Not set')
        ->assertSee('Server fallback')
        ->assertSee('Social profiles')
        ->assertSee('None')
        ->assertSee('Empty')
        ->assertDontSee('Save changes');
});

it('persists one changed text value once and skips unchanged commits and duplicate blur paths', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, ['public_email' => 'before@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    $component = Livewire::test(General::class)
        ->assertOk()
        ->assertDontSee('Save changes')
        ->set('data.public_email', 'after@example.invalid');

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('after@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 1);

    $component->call('persistChangedField', 'public_email')
        ->call('persistChangedField', 'public_email');

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('after@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 1)
        ->and(PublicContentSetting::query()->where('scope', PublicContentSetting::SCOPE_GENERAL)->count())->toBe(1);
});

it('uses only event-driven lazy text persistence with no debounce or timer autosave', function (): void {
    $pageSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $viewSource = file_get_contents(resource_path('views/filament/pages/general.blade.php'));

    expect($pageSource)->toBeString()
        ->and(substr_count($pageSource, '->lazy()'))->toBe(5)
        ->and($pageSource)->not->toContain('live(debounce:')
        ->and($pageSource)->not->toContain('debounce(')
        ->and($pageSource)->not->toContain('setTimeout')
        ->and($pageSource)->not->toContain('wire:model.debounce')
        ->and($viewSource)->toBeString()
        ->and($viewSource)->not->toContain('wire:model.debounce')
        ->and($pageSource)->toContain("'x-on:keydown.enter.prevent' => '\$event.target.blur()'");
});

it('keeps invalid event persistence visible as a field error without replacing persisted data', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, ['public_email' => 'valid@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.public_email', 'not-an-email')
        ->assertHasErrors(['data.public_email']);

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('valid@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore);
});

it('persists toggle select and media changes on their real state-change lifecycle', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'show_public_email' => true,
        'social_links' => [
            ['platform' => 'facebook', 'url' => 'https://example.invalid/profile', 'visible' => true],
        ],
    ]);
    $favicon = generalStatusFavicon();
    $auditBefore = generalSettingsAuditCount();

    $component = Livewire::test(General::class);

    $component->set('data.show_public_email', false);
    expect(PublicContentSetting::general()->getAttribute('show_public_email'))->toBeFalse()
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 1);

    $component->set('data.favicon_media_asset_id', $favicon->id);
    expect((int) PublicContentSetting::general()->getAttribute('favicon_media_asset_id'))->toBe((int) $favicon->id)
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 2);

    $socialState = $component->get('data.social_links');
    expect($socialState)->toBeArray()->not->toBeEmpty();
    $itemKey = array_key_first($socialState);

    $component->set("data.social_links.{$itemKey}.platform", 'instagram');
    expect(PublicContentSetting::general()->getAttribute('social_links')[0]['platform'])->toBe('instagram')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 3);
});

it('persists social order visibility and legal text through the singleton page', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => true],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook', 'visible' => true],
        ],
        'legal_disclaimer' => 'Original legal text.',
    ]);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.social_links', [
            ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook', 'visible' => false],
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => true],
        ])
        ->set('data.legal_disclaimer', 'Updated legal text.');

    $fresh = PublicContentSetting::general();

    expect($fresh->getAttribute('social_links')[0]['platform'])->toBe('facebook')
        ->and($fresh->getAttribute('social_links')[0]['visible'])->toBeFalse()
        ->and($fresh->getAttribute('social_links')[1]['platform'])->toBe('instagram')
        ->and($fresh->getAttribute('legal_disclaimer'))->toBe('Updated legal text.')
        ->and(generalSettingsAuditCount())->toBe($auditBefore + 2);
});

it('keeps a changed text value persisted before the next internal admin navigation', function (): void {
    Livewire::test(General::class)
        ->set('data.default_media_copyright_notice', 'Navigation-safe notice');

    $this->get('/admin/media-files')->assertOk();

    expect(PublicContentSetting::general()->getAttribute('default_media_copyright_notice'))->toBe('Navigation-safe notice');
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

it('keeps General presentation flat and leaves the compatibility resource out of navigation', function (): void {
    $pageSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $viewSource = file_get_contents(resource_path('views/filament/pages/general.blade.php'));
    $resourceSource = file_get_contents(app_path('Filament/Resources/PublicContentSettings/PublicContentSettingResource.php'));

    expect($viewSource)->toBeString()
        ->and($viewSource)->toContain('general-workspace__sheet')
        ->and($viewSource)->not->toContain('general-workspace__form')
        ->and($pageSource)->toBeString()
        ->and($pageSource)->toContain("'Site identity'")
        ->and($pageSource)->toContain("'Public contact'")
        ->and($pageSource)->toContain("'Contact delivery'")
        ->and($pageSource)->toContain("'Social links'")
        ->and($pageSource)->toContain("'Legal & media'")
        ->and($pageSource)->not->toContain('AdminForm::section')
        ->and($resourceSource)->toBeString()
        ->and($resourceSource)->toContain('protected static bool $shouldRegisterNavigation = false;');
});
