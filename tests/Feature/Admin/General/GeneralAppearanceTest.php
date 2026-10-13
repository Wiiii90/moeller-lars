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

it('keeps background mode geometry stable and maps shared color slots to existing fields', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#123456',
        'background_gradient_start' => '#112233',
        'background_gradient_end' => '#445566',
    ]);

    $component = Livewire::test(General::class)
        ->assertSet('data.background_primary_color', '#123456')
        ->assertSet('data.background_secondary_color', '#445566')
        ->set('data.background_mode', 'gradient')
        ->call('syncAppearanceControlState')
        ->assertSet('data.background_primary_color', '#112233')
        ->call('persistAppearanceColor', 'primary', '#AABBCC')
        ->call('persistAppearanceColor', 'secondary', '#DDEEFF');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('background_gradient_start'))->toBe('#AABBCC')
        ->and($fresh->getAttribute('background_gradient_end'))->toBe('#DDEEFF')
        ->and($fresh->getAttribute('background_color'))->toBe('#123456');

    $component
        ->set('data.background_mode', 'solid')
        ->call('syncAppearanceControlState')
        ->call('persistAppearanceColor', 'primary', '#ABCDEF');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('background_color'))->toBe('#ABCDEF')
        ->and($fresh->getAttribute('background_gradient_start'))->toBe('#AABBCC')
        ->and($fresh->getAttribute('background_gradient_end'))->toBe('#DDEEFF');
});


it('treats legacy default background as solid 777777 without writing on mount', function (): void {
    $settings = PublicContentSetting::general();
    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'background_mode' => null,
        'background_color' => '#123456',
    ]);
    $auditBefore = generalSettingsAuditCount();

    Livewire::test(General::class)
        ->assertSet('data.background_mode', 'solid')
        ->assertSet('data.background_primary_color', '#777777');

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('background_mode'))->toBeNull()
        ->and($fresh->getAttribute('background_color'))->toBe('#123456')
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


it('renders only committed default solid and gradient appearance through a request-local CSP nonce', function (): void {
    $actor = User::query()->firstOrFail();
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

    expect($defaultCsp)->toContain("style-src 'self' 'nonce-{$defaultNonce}'")
        ->and($defaultCsp)->not->toContain("'unsafe-inline'")
        ->and($defaultHtml)->not->toContain('--public-page:');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'solid',
        'background_color' => '#123456',
    ]);

    $stagedSolidHtml = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($stagedSolidHtml)->not->toContain('--public-page: #123456');

    app(PublicationService::class)->commit($actor, 'Publish solid appearance');
    $solidResponse = $this->get(route('home'))->assertOk();
    $solidCsp = (string) $solidResponse->headers->get('Content-Security-Policy');
    $solidHtml = (string) $solidResponse->getContent();
    $solidNonce = generalPublicStyleNonce($solidResponse);

    expect($solidNonce)->not->toBe($defaultNonce)
        ->and($solidCsp)->toContain("'nonce-{$solidNonce}'")
        ->and($solidCsp)->not->toContain("'unsafe-inline'")
        ->and($solidHtml)->toContain('--public-page: #123456');
    generalAssertPublicStyleUsesNonce($solidHtml, $solidNonce, '--public-page: #123456');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#112233',
        'background_gradient_end' => '#AABBCC',
        'background_gradient_angle' => 45,
    ]);

    $stagedGradientHtml = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($stagedGradientHtml)->toContain('--public-page: #123456')
        ->and($stagedGradientHtml)->not->toContain('--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed');

    app(PublicationService::class)->commit($actor, 'Publish gradient appearance');
    $gradientResponse = $this->get(route('home'))->assertOk();
    $gradientCsp = (string) $gradientResponse->headers->get('Content-Security-Policy');
    $gradientHtml = (string) $gradientResponse->getContent();
    $gradientNonce = generalPublicStyleNonce($gradientResponse);

    expect($gradientNonce)->not->toBe($solidNonce)
        ->and($gradientCsp)->toContain("'nonce-{$gradientNonce}'")
        ->and($gradientCsp)->not->toContain("'unsafe-inline'")
        ->and($gradientHtml)->toContain('--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed')
        ->and($gradientHtml)->not->toContain('javascript:');
    generalAssertPublicStyleUsesNonce(
        $gradientHtml,
        $gradientNonce,
        '--public-page: linear-gradient(45deg, #112233, #AABBCC) fixed',
    );
});
