<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
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

it('persists one changed text value once and skips unchanged commits and duplicate blur paths', function (): void {
    $settings = PublicContentSetting::general();
    $id = $settings->getKey();
    app(AdminSettingsService::class)->updatePublicContent($settings, ['public_email' => 'before@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    $component = Livewire::test(EditPublicContentSetting::class, ['record' => $id])
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
    $resourceSource = file_get_contents(app_path('Filament/Resources/PublicContentSettings/PublicContentSettingResource.php'));
    $pageSource = file_get_contents(app_path('Filament/Resources/PublicContentSettings/Pages/EditPublicContentSetting.php'));
    $viewSource = file_get_contents(resource_path('views/filament/resources/public-content-settings/pages/edit-public-content-setting.blade.php'));

    expect($resourceSource)->toBeString()
        ->and(substr_count($resourceSource, '->lazy()'))->toBe(5)
        ->and($resourceSource)->not->toContain('live(debounce:')
        ->and($resourceSource)->not->toContain('debounce(')
        ->and($resourceSource)->not->toContain('setTimeout')
        ->and($resourceSource)->not->toContain('wire:model.debounce')
        ->and($pageSource)->toBeString()
        ->and($pageSource)->not->toContain('setTimeout')
        ->and($viewSource)->toBeString()
        ->and($viewSource)->not->toContain('wire:model.debounce')
        ->and($resourceSource)->toContain("'x-on:keydown.enter.prevent' => '\$event.target.blur()'");
});

it('keeps invalid event persistence visible as a field error without replacing persisted data', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, ['public_email' => 'valid@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(EditPublicContentSetting::class, ['record' => $settings->getKey()])
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

    $component = Livewire::test(EditPublicContentSetting::class, ['record' => $settings->getKey()]);

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

it('keeps a changed text value persisted before the next internal admin navigation', function (): void {
    $settings = PublicContentSetting::general();

    Livewire::test(EditPublicContentSetting::class, ['record' => $settings->getKey()])
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
