<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\AdminNotification;
use App\Domain\Admin\AdminActivityNotificationContext;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('saves a first solid color as one complete action and repeats silently', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['background_mode' => null, 'background_color' => '#123456']);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->call('persistAppearanceColor', 'primary', '#ABCDEF');
    expect(PublicContentSetting::general()->background_mode)->toBe('solid')
        ->and(PublicContentSetting::general()->background_color)->toBe('#ABCDEF')
        ->and(generalSettingsAuditCount())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $component->call('persistAppearanceColor', 'primary', '#ABCDEF')->assertNotDispatched('admin-header-notification');
    expect(generalSettingsAuditCount())->toBe($before + 1);
});

it('rolls back the complete first color action when its color is invalid', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['background_mode' => null, 'background_color' => '#123456']);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->call('persistAppearanceColor', 'primary', 'invalid')
        ->assertHasErrors(['data.background_primary_color']);
    $saved = PublicContentSetting::query()->findOrFail(1);
    expect($saved->background_mode)->toBeNull()->and($saved->background_color)->toBe('#123456')
        ->and(generalSettingsAuditCount())->toBe($before)
        ->and(AdminNotification::query()->where('status', 'success')->count())->toBe(0)
        ->and(AdminNotification::query()->where('status', 'danger')->count())->toBe(1);
    $component->call('persistAppearanceColor', 'primary', '#ABCDEF')->assertHasNoErrors();
    expect(PublicContentSetting::general()->background_mode)->toBe('solid')
        ->and(PublicContentSetting::general()->background_color)->toBe('#ABCDEF')
        ->and(generalSettingsAuditCount())->toBe($before + 1)
        ->and(AdminNotification::query()->where('status', 'success')->count())->toBe(1);
});

it('commits a mode switch and its effective solid color with one feedback', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['background_mode' => null, 'background_color' => '#123456']);
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();
    Livewire::test(General::class)->set('data.background_mode', 'solid')->assertDispatched('admin-header-notification');
    expect(PublicContentSetting::general()->background_mode)->toBe('solid')
        ->and(PublicContentSetting::general()->background_color)->toBe('#777777')
        ->and(generalSettingsAuditCount())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1);
});

require_once __DIR__.'/GeneralFixtures.php';

it('updates separate persisted solid and gradient colors through the shared controls', function (): void {
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


it('preserves the original white Menu and Page until independent global paint is chosen', function (): void {
    $settings = PublicContentSetting::general();

    expect(PublicAppearance::surfaceCss($settings, 'menu_background'))->toBeNull()
        ->and(PublicAppearance::surfaceCss($settings, 'page_background'))->toBeNull();

    $before = generalSettingsAuditCount();
    Livewire::test(General::class)
        ->assertSet('data.menu_background_mode', 'solid')
        ->assertSet('data.page_background_mode', 'solid')
        ->assertSet('data.menu_background_primary_color', '#FFFFFF')
        ->assertSet('data.page_background_primary_color', '#FFFFFF');

    expect(generalSettingsAuditCount())->toBe($before)
        ->and(PublicContentSetting::general()->getAttribute('menu_background_mode'))->toBeNull()
        ->and(PublicContentSetting::general()->getAttribute('page_background_mode'))->toBeNull();

    app(AdminSettingsService::class)->updatePublicContent($settings, [
        'menu_background_mode' => 'solid',
        'menu_background_color' => 'fefdfc',
        'page_background_mode' => 'gradient',
        'page_background_gradient_start' => '#a1b2c3',
        'page_background_gradient_end' => '#ddeeff',
        'page_background_gradient_angle' => 45,
    ]);

    $settings = PublicContentSetting::general();
    expect(PublicAppearance::surfaceCss($settings, 'menu_background'))->toBe('#FEFDFC')
        ->and(PublicAppearance::surfaceCss($settings, 'page_background'))->toBe('linear-gradient(45deg, #A1B2C3, #DDEEFF)')
        ->and(PublicAppearance::backgroundCss($settings))->toBeNull();

    expect(fn () => app(AdminSettingsService::class)->updatePublicContent($settings, [
        'page_background_gradient_angle' => 370,
    ]))->toThrow(ValidationException::class);
    expect(fn () => app(AdminSettingsService::class)->updatePublicContent($settings, [
        'menu_background_gradient_start' => 'url(javascript:alert(1))',
    ]))->toThrow(ValidationException::class);
});

it('autosaves Menu and Page colors independently using the shared General controls', function (): void {
    app(AdminActivityNotificationContext::class)->discard();
    $before = generalSettingsAuditCount();

    $component = Livewire::test(General::class)
        ->call('persistSurfaceColor', 'menu_background', 'primary', '#ABCDEF')
        ->assertHasNoErrors()
        ->call('persistSurfaceColor', 'page_background', 'primary', '#123456')
        ->assertHasNoErrors();

    $settings = PublicContentSetting::general();
    expect($settings->getAttribute('menu_background_mode'))->toBe('solid')
        ->and($settings->getAttribute('menu_background_color'))->toBe('#ABCDEF')
        ->and($settings->getAttribute('page_background_mode'))->toBe('solid')
        ->and($settings->getAttribute('page_background_color'))->toBe('#123456')
        ->and(generalSettingsAuditCount())->toBe($before + 2);

    $component->call('persistSurfaceColor', 'menu_background', 'primary', '#ABCDEF')
        ->assertNotDispatched('admin-header-notification');
    expect(generalSettingsAuditCount())->toBe($before + 2);
});

it('publishes global Menu and Page paints only on Commit, with nonce-authorized CSS', function (): void {
    $actor = User::query()->firstOrFail();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'menu_background_mode' => 'solid',
        'menu_background_color' => '#AABBCC',
        'page_background_mode' => 'gradient',
        'page_background_gradient_start' => '#112233',
        'page_background_gradient_end' => '#445566',
        'page_background_gradient_angle' => 90,
    ]);

    $publicBefore = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($publicBefore)->not->toContain('--public-menu-background: #AABBCC')
        ->and($publicBefore)->not->toContain('--public-content-background: linear-gradient(90deg, #112233, #445566)');
    $preview = (string) $this->get('/preview')->assertOk()->getContent();
    expect($preview)->toContain('--public-menu-background: #AABBCC')
        ->and($preview)->toContain('--public-content-background: linear-gradient(90deg, #112233, #445566)');

    app(PublicationService::class)->commit($actor, 'Publish global Menu and Page colors');

    $response = $this->get(route('home'))->assertOk();
    $html = (string) $response->getContent();
    $nonce = generalPublicStyleNonce($response);
    expect($html)->toContain('--public-menu-background: #AABBCC')
        ->and($html)->toContain('--public-content-background: linear-gradient(90deg, #112233, #445566)')
        ->and((string) $response->headers->get('Content-Security-Policy'))->not->toContain("'unsafe-inline'");
    generalAssertPublicStyleUsesNonce($html, $nonce, '--public-menu-background: #AABBCC');
    generalAssertPublicStyleUsesNonce($html, $nonce, '--public-content-background: linear-gradient(90deg, #112233, #445566)');
});
