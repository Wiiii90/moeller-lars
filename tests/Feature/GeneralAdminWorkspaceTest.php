<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
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

function generalPublicStyleNonce($response): string
{
    $csp = (string) $response->headers->get('Content-Security-Policy');
    $matched = preg_match("/style-src[^;]*'nonce-([^']+)'/", $csp, $matches);

    expect($matched)->toBe(1)
        ->and($matches)->toHaveKey(1);

    return $matches[1];
}

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

it('renders six shared status metrics and the four accepted General sections', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#334455',
        'public_email' => 'public@example.invalid',
        'show_public_email' => true,
        'contact_recipient_email' => 'delivery@example.invalid',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => true],
        ],
        'default_media_copyright_notice' => 'Default copyright',
        'legal_disclaimer' => 'Global legal text.',
    ]);

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('Appearance')
        ->assertSee('Solid')
        ->assertSee('Public email')
        ->assertSee('Contact delivery')
        ->assertSee('Social profiles')
        ->assertSee('Media copyright')
        ->assertSee('Legal text')
        ->assertSee('Contact')
        ->assertSee('Social links')
        ->assertSee('Legal &amp; media', false)
        ->assertDontSee('Site identity')
        ->assertDontSee('Public contact')
        ->assertDontSee('Save changes');

    $metricSource = file_get_contents(resource_path('views/filament/schemas/components/general-status-metrics.blade.php'));
    expect(substr_count((string) $metricSource, '<x-admin.metric '))->toBe(6);
});

it('uses the shared form and boolean grammar without restoring rejected General presentation', function (): void {
    $pageSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $viewSource = file_get_contents(resource_path('views/filament/pages/general.blade.php'));
    $socialSource = file_get_contents(resource_path('views/filament/schemas/components/general-social-links.blade.php'));
    $booleanSource = file_get_contents(app_path('Filament/Support/AdminBooleanControl.php'));
    $formsCss = file_get_contents(resource_path('css/admin/forms.css'));

    expect($pageSource)->toContain("AdminForm::section('Appearance', 'admin-form-controls')")
        ->and($pageSource)->toContain("AdminForm::section('Contact', 'admin-form-controls')")
        ->and($pageSource)->toContain("AdminForm::section('Social links', 'admin-form-controls')")
        ->and($pageSource)->toContain("AdminForm::section('Legal & media', 'admin-form-controls')")
        ->and(substr_count((string) $pageSource, "'admin-form-controls'"))->toBe(4)
        ->and($pageSource)->toContain('MediaAssetSelect::make')
        ->and($pageSource)->not->toContain('Repeater::')
        ->and($pageSource)->not->toContain('Toggle::')
        ->and($pageSource)->not->toContain('settingsSection(')
        ->and($viewSource)->not->toContain('general-workspace__sheet')
        ->and($socialSource)->toContain('<x-admin.table')
        ->and($socialSource)->toContain('AdminBooleanControl::options')
        ->and($socialSource)->toContain('class="admin-form-control admin-boolean-control"')
        ->and($socialSource)->toContain('class="admin-action is-danger"')
        ->and($socialSource)->toContain('>Delete</button>')
        ->and($socialSource)->toContain('<x-admin.add-row')
        ->and($socialSource)->not->toContain('trash')
        ->and($booleanSource)->toContain('->native()')
        ->and($booleanSource)->not->toContain('native(false)')
        ->and($booleanSource)->toContain("'class' => 'admin-form-control admin-boolean-control'")
        ->and($formsCss)->toContain('.admin-form-controls .fi-input-wrp')
        ->and($formsCss)->toContain('.admin-form-controls .fi-input')
        ->and($formsCss)->toContain('.admin-form-controls .fi-select-input')
        ->and($formsCss)->toContain('.admin-form-controls textarea.fi-input')
        ->and($formsCss)->not->toContain('.general-');
});

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

it('keeps invalid event persistence visible without replacing persisted data', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['public_email' => 'valid@example.invalid']);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.public_email', 'not-an-email')
        ->assertHasErrors(['data.public_email']);

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('valid@example.invalid')
        ->and(generalSettingsAuditCount())->toBe($auditBefore);
});

it('validates and canonicalizes structured public background settings', function (): void {
    $settings = PublicContentSetting::general();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'background_mode' => 'gradient',
        'background_gradient_start' => 'a1b2c3',
        'background_gradient_end' => '#d4e5f6',
        'background_gradient_angle' => 315,
    ]);

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('background_mode'))->toBe('gradient')
        ->and($fresh->getAttribute('background_gradient_start'))->toBe('#A1B2C3')
        ->and($fresh->getAttribute('background_gradient_end'))->toBe('#D4E5F6')
        ->and($fresh->getAttribute('background_gradient_angle'))->toBe(315)
        ->and(PublicAppearance::backgroundCss($fresh))->toBe('linear-gradient(315deg, #A1B2C3, #D4E5F6) fixed');

    expect(fn () => app(AdminSettingsService::class)->updatePublicContent($fresh, ['background_mode' => 'url(javascript:alert(1))']))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(AdminSettingsService::class)->updatePublicContent($fresh, ['background_color' => '#fff;position:fixed']))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(AdminSettingsService::class)->updatePublicContent($fresh, ['background_gradient_angle' => 361]))
        ->toThrow(ValidationException::class);
});

it('renders default solid and gradient appearance through a request-local CSP nonce', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'background_mode' => null,
        'background_color' => null,
        'background_gradient_start' => null,
        'background_gradient_end' => null,
        'background_gradient_angle' => null,
    ]);

    $defaultResponse = $this->get(route('home'))->assertOk();
    $defaultCsp = (string) $defaultResponse->headers->get('Content-Security-Policy');
    $defaultHtml = (string) $defaultResponse->getContent();
    $defaultNonce = generalPublicStyleNonce($defaultResponse);

    expect($defaultCsp)->toContain("style-src 'self' 'nonce-")
        ->and($defaultCsp)->not->toContain("'unsafe-inline'")
        ->and($defaultHtml)->not->toContain('<style nonce=')
        ->and($defaultHtml)->not->toContain('style="--public-page:')
        ->and($defaultHtml)->not->toContain('<html style=');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#123456',
    ]);
    $solidResponse = $this->get(route('home'))->assertOk();
    $solidCsp = (string) $solidResponse->headers->get('Content-Security-Policy');
    $solidHtml = (string) $solidResponse->getContent();
    $solidNonce = generalPublicStyleNonce($solidResponse);

    expect($solidNonce)->not->toBe($defaultNonce)
        ->and($solidCsp)->not->toContain("'unsafe-inline'")
        ->and($solidHtml)->toContain('<style nonce="'.$solidNonce.'">')
        ->and($solidHtml)->toContain(':root { --public-page: #123456; }')
        ->and($solidHtml)->not->toContain('style="--public-page:')
        ->and($solidHtml)->not->toContain('<html style=');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#112233',
        'background_gradient_end' => '#AABBCC',
        'background_gradient_angle' => 45,
    ]);
    $gradientResponse = $this->get(route('home'))->assertOk();
    $gradientCsp = (string) $gradientResponse->headers->get('Content-Security-Policy');
    $gradientHtml = (string) $gradientResponse->getContent();
    $gradientNonce = generalPublicStyleNonce($gradientResponse);

    expect($gradientNonce)->not->toBe($solidNonce)
        ->and($gradientCsp)->not->toContain("'unsafe-inline'")
        ->and($gradientHtml)->toContain('<style nonce="'.$gradientNonce.'">')
        ->and($gradientHtml)->toContain(':root { --public-page: linear-gradient(45deg, #112233, #AABBCC) fixed; }')
        ->and($gradientHtml)->not->toContain('style="--public-page:')
        ->and($gradientHtml)->not->toContain('<html style=')
        ->and($gradientHtml)->not->toContain('javascript:');
});

it('persists social link add update visibility order and delete without a repeater', function (): void {
    $auditBefore = generalSettingsAuditCount();
    $component = Livewire::test(General::class)
        ->call('addSocialLink')
        ->call('updateSocialLink', 0, 'url', 'https://example.invalid/profile')
        ->call('updateSocialLink', 0, 'platform', 'instagram');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('social_links')[0]['platform'])->toBe('instagram')
        ->and($fresh->getAttribute('social_links')[0]['visible'])->toBeTrue();

    $component->call('updateSocialLink', 0, 'visible', '0');
    expect(PublicContentSetting::general()->getAttribute('social_links')[0]['visible'])->toBeFalse();

    $component->call('deleteSocialLink', 0);
    expect(PublicContentSetting::general()->getAttribute('social_links'))->toBe([])
        ->and(generalSettingsAuditCount())->toBeGreaterThan($auditBefore);
});

it('persists boolean media copyright and legal settings through event-driven controls', function (): void {
    $favicon = generalStatusFavicon();
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->set('data.show_public_email', false)
        ->set('data.favicon_media_asset_id', $favicon->id)
        ->set('data.default_media_copyright_notice', '  Copyright notice  ')
        ->set('data.legal_disclaimer', 'Legal text.');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('show_public_email'))->toBeFalse()
        ->and((int) $fresh->getAttribute('favicon_media_asset_id'))->toBe((int) $favicon->id)
        ->and($fresh->getAttribute('default_media_copyright_notice'))->toBe('Copyright notice')
        ->and($fresh->getAttribute('legal_disclaimer'))->toBe('Legal text.')
        ->and(generalSettingsAuditCount())->toBeGreaterThan($auditBefore);
});

it('keeps text persistence event-driven without debounce or timer autosave', function (): void {
    $pageSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $viewSource = file_get_contents(resource_path('views/filament/pages/general.blade.php'));

    expect($pageSource)->not->toContain('live(debounce:')
        ->and($pageSource)->not->toContain('debounce(')
        ->and($pageSource)->not->toContain('setTimeout')
        ->and($viewSource)->not->toContain('wire:model.debounce')
        ->and($pageSource)->toContain("'x-on:keydown.enter.prevent' => '\$event.target.blur()'");
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

    $results = MediaAssetSelect::searchOptions('picker-match', imagesOnly: true);

    expect(array_keys($results))->toBe([(int) $image->id]);
});
