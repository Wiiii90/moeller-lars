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

it('builds multistop Linear, Radial and Conic global paints with optional repetition', function (): void {
    $service = app(AdminSettingsService::class);
    $settings = PublicContentSetting::general();
    $stops = [
        ['color' => '#112233', 'position' => 0],
        ['color' => '#445566', 'position' => 40],
        ['color' => '#778899', 'position' => 100],
    ];

    $service->updatePublicContent($settings, [
        'background_mode' => 'gradient',
        'background_gradient_angle' => 45,
        'background_gradient_stops' => $stops,
        'menu_background_mode' => 'radial',
        'menu_background_gradient_shape' => 'ellipse',
        'menu_background_gradient_center_x' => 25,
        'menu_background_gradient_center_y' => 75,
        'menu_background_gradient_repeating' => true,
        'menu_background_gradient_repeat_span' => 80,
        'menu_background_gradient_stops' => $stops,
        'page_background_mode' => 'conic',
        'page_background_gradient_angle' => 30,
        'page_background_gradient_center_x' => 10,
        'page_background_gradient_center_y' => 80,
        'page_background_gradient_repeating' => true,
        'page_background_gradient_repeat_span' => 90,
        'page_background_gradient_stops' => $stops,
    ]);

    $settings = PublicContentSetting::general();
    expect(PublicAppearance::backgroundCss($settings))
        ->toBe('linear-gradient(45deg, #112233 0%, #445566 40%, #778899 100%) fixed')
        ->and(PublicAppearance::surfaceCss($settings, 'menu_background'))
        ->toBe('repeating-radial-gradient(ellipse at 25% 75%, #112233 0px, #445566 32px, #778899 80px)')
        ->and(PublicAppearance::surfaceCss($settings, 'page_background'))
        ->toBe('repeating-conic-gradient(from 30deg at 10% 80%, #112233 0deg, #445566 36deg, #778899 90deg)');
});

it('normalizes and validates variable color stops without permitting arbitrary CSS', function (): void {
    $settings = PublicContentSetting::general();
    $service = app(AdminSettingsService::class);

    $service->updatePublicContent($settings, [
        'menu_background_mode' => 'gradient',
        'menu_background_gradient_stops' => [
            ['color' => '#112233', 'position' => 0],
            ['color' => 'abcdef', 'position' => '70'],
            ['color' => '#445566', 'position' => '30'],
            ['color' => '#778899', 'position' => 100],
        ],
    ]);

    $saved = PublicContentSetting::general();
    expect($saved->getAttribute('menu_background_gradient_stops'))->toBe([
        ['color' => '#112233', 'position' => 0],
        ['color' => '#445566', 'position' => 30],
        ['color' => '#ABCDEF', 'position' => 70],
        ['color' => '#778899', 'position' => 100],
    ]);

    foreach ([
        [['color' => '#111111', 'position' => 0]],
        [
            ['color' => 'javascript:alert(1)', 'position' => 0],
            ['color' => '#FFFFFF', 'position' => 100],
        ],
        [
            ['color' => '#111111', 'position' => -1],
            ['color' => '#FFFFFF', 'position' => 100],
        ],
    ] as $invalid) {
        expect(fn () => $service->updatePublicContent(PublicContentSetting::general(), [
            'page_background_gradient_stops' => $invalid,
        ]))->toThrow(ValidationException::class);
    }
    expect(fn () => $service->updatePublicContent(PublicContentSetting::general(), [
        'page_background_gradient_center_x' => 101,
    ]))->toThrow(ValidationException::class);
    expect(fn () => $service->updatePublicContent(PublicContentSetting::general(), [
        'page_background_gradient_shape' => 'url(evil)',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $service->updatePublicContent(PublicContentSetting::general(), [
        'page_background_gradient_repeat_span' => 0,
    ]))->toThrow(ValidationException::class);
});

it('leaves legacy two-color gradients unchanged until the new stops are edited', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#123456',
        'background_gradient_end' => '#AABBCC',
        'background_gradient_angle' => 24,
    ]);
    expect(PublicAppearance::backgroundCss(PublicContentSetting::general()))
        ->toBe('linear-gradient(24deg, #123456, #AABBCC) fixed');
    Livewire::test(General::class)
        ->assertSet('data.background_gradient_stops', [
            ['color' => '#123456', 'position' => 0],
            ['color' => '#AABBCC', 'position' => 100],
        ]);
    expect(PublicContentSetting::general()->getAttribute('background_gradient_stops'))->toBeNull();
});

it('keeps Working multi-stop appearance distinct from Committed until publication', function (): void {
    $actor = User::query()->firstOrFail();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'page_background_mode' => 'conic',
        'page_background_gradient_stops' => [
            ['color' => '#FF0000', 'position' => 0],
            ['color' => '#00FF00', 'position' => 50],
            ['color' => '#0000FF', 'position' => 100],
        ],
    ]);
    expect((string) $this->get(route('home'))->assertOk()->getContent())
        ->not->toContain('conic-gradient(');
    expect((string) $this->get('/preview')->assertOk()->getContent())
        ->toContain('conic-gradient(');
    app(PublicationService::class)->commit($actor, 'Publish multi-stop Page appearance');
    expect((string) $this->get(route('home'))->assertOk()->getContent())
        ->toContain('conic-gradient(');
});

it('keeps Appearance gradient actions on the shared line-based admin control contract', function (): void {
    $component = Livewire::test(General::class);
    $component
        ->assertSet('data.background_gradient_stops', [
            ['color' => '#777777', 'position' => 0],
            ['color' => '#777777', 'position' => 100],
        ])
        ->set('data.menu_background_mode', 'gradient')
        ->assertHasNoErrors()
        ->set('data.menu_background_gradient_repeating', '1')
        ->assertHasNoErrors();

    expect(PublicContentSetting::general()->getAttribute('menu_background_gradient_repeating'))->toBeTrue();
    $component->set('data.menu_background_gradient_repeating', '0')->assertHasNoErrors();
    expect(PublicContentSetting::general()->getAttribute('menu_background_gradient_repeating'))->toBeFalse();
});

it('does not persist color defaults on mount', function (): void {
    Livewire::test(General::class)->assertHasNoErrors();
    expect(PublicContentSetting::general()->getAttribute('header_name_color'))->toBeNull()
        ->and(PublicContentSetting::general()->getAttribute('header_divider_color'))->toBeNull();
});

it('autosaves an inline Solid ColorPicker independently of gradient stops', function (): void {
    Livewire::test(General::class)
        ->set('data.menu_background_color', '#ABCDEF')
        ->assertHasNoErrors();

    expect(PublicContentSetting::general()->getAttribute('menu_background_color'))->toBe('#ABCDEF')
        ->and(PublicContentSetting::general()->getAttribute('menu_background_gradient_stops'))->toBeNull();
});

it('saves color table rows sorted by the chosen numeric position', function (): void {
    Livewire::test(General::class)
        ->set('data.page_background_mode', 'gradient')
        ->set('data.page_background_gradient_stops', [
            ['color' => '#112233', 'position' => 10],
            ['color' => '#778899', 'position' => 75],
            ['color' => '#445566', 'position' => 50],
        ])
        ->call('persistAppearanceTable', 'page_background')
        ->assertHasNoErrors();

    expect(PublicAppearance::stops(PublicContentSetting::general(), 'page_background'))->toBe([
        ['color' => '#112233', 'position' => 10],
        ['color' => '#445566', 'position' => 50],
        ['color' => '#778899', 'position' => 75],
    ]);
});






it('preserves the inherited title color until the artist explicitly sets a title paint', function (): void {
    $settings = PublicContentSetting::general();
    $originalColor = $settings->getAttribute('header_name_color');
    expect($settings->getAttribute('header_name_mode'))->toBeNull()
        ->and(PublicAppearance::titleGradientCss($settings))->toBeNull();

    Livewire::test(General::class)
        ->assertSet('data.header_name_mode', 'solid')
        ->assertSet('data.header_name_gradient_stops', [
            ['color' => $originalColor ?? PublicAppearance::DEFAULT_TITLE_GRADIENT_COLOR, 'position' => 0],
            ['color' => $originalColor ?? PublicAppearance::DEFAULT_TITLE_GRADIENT_COLOR, 'position' => 100],
        ]);
    expect(PublicContentSetting::general()->getAttribute('header_name_mode'))->toBeNull()
        ->and(PublicContentSetting::general()->getAttribute('header_name_color'))->toBe($originalColor);

    $public = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($public)->not->toContain('site-header--gradient-title')
        ->not->toContain('--public-title-paint:');
});

it('publishes a multistop title gradient through the existing Working and Committed pipeline', function (): void {
    $actor = User::query()->firstOrFail();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_name_mode' => 'conic',
        'header_name_gradient_angle' => 45,
        'header_name_gradient_center_x' => 35,
        'header_name_gradient_center_y' => 65,
        'header_name_gradient_stops' => [
            ['color' => '#112233', 'position' => 0],
            ['color' => '#445566', 'position' => 60],
            ['color' => '#778899', 'position' => 100],
        ],
    ]);

    $css = 'conic-gradient(from 45deg at 35% 65%, #112233 0%, #445566 60%, #778899 100%)';
    expect(PublicAppearance::titleGradientCss(PublicContentSetting::general()))->toBe($css);
    expect((string) $this->get(route('home'))->assertOk()->getContent())
        ->not->toContain('--public-title-paint: '.$css);

    $preview = $this->get('/preview')->assertOk();
    $previewHtml = (string) $preview->getContent();
    expect($previewHtml)->toContain('--public-title-paint: '.$css)
        ->toContain('site-header--gradient-title');
    generalAssertPublicStyleUsesNonce($previewHtml, generalPublicStyleNonce($preview), '--public-title-paint: '.$css);

    app(PublicationService::class)->commit($actor, 'Publish artist title gradient');
    $public = $this->get(route('home'))->assertOk();
    expect((string) $public->getContent())->toContain('--public-title-paint: '.$css)
        ->toContain('site-header--gradient-title');
    generalAssertPublicStyleUsesNonce((string) $public->getContent(), generalPublicStyleNonce($public), '--public-title-paint: '.$css);
});

it('reuses the same title color-table persistence as Background, Menu and Page', function (): void {
    Livewire::test(General::class)
        ->set('data.header_name_mode', 'gradient')
        ->set('data.header_name_gradient_stops', [
            ['color' => '#AABBCC', 'position' => 0],
            ['color' => '#DDEEFF', 'position' => 50],
            ['color' => '#001122', 'position' => 100],
        ])
        ->call('persistAppearanceTable', 'header_name')
        ->assertHasNoErrors();

    expect(PublicAppearance::stops(PublicContentSetting::general(), 'header_name'))->toBe([
        ['color' => '#AABBCC', 'position' => 0],
        ['color' => '#DDEEFF', 'position' => 50],
        ['color' => '#001122', 'position' => 100],
    ]);
});

it('preserves the established solid menu divider color when the gradient editor opens', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_mode' => 'menu',
        'header_divider_color' => '#ABCDEF',
    ]);
    $settings = PublicContentSetting::general();
    expect(PublicAppearance::dividerGradientCss($settings))->toBeNull();

    Livewire::test(General::class)
        ->assertSet('data.header_divider_paint_mode', 'solid')
        ->assertSet('data.header_divider_color', '#ABCDEF');

    expect(PublicContentSetting::general()->header_divider_paint_mode)->toBeNull()
        ->and(PublicContentSetting::general()->header_divider_color)->toBe('#ABCDEF');

    Livewire::test(General::class)
        ->set('data.header_divider_color', '#112233')
        ->assertHasNoErrors();

    expect(PublicContentSetting::general()->header_divider_color)->toBe('#112233')
        ->and(PublicContentSetting::general()->header_divider_paint_mode)->toBe('solid');
});

it('publishes the menu divider gradient through existing nonce-based Working and Committed styles', function (): void {
    $actor = User::query()->firstOrFail();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_mode' => 'menu',
        'header_divider_color' => '#123456',
        'header_divider_paint_mode' => 'gradient',
        'header_divider_paint_gradient_angle' => 90,
        'header_divider_paint_gradient_stops' => [
            ['color' => '#112233', 'position' => 0],
            ['color' => '#445566', 'position' => 40],
            ['color' => '#778899', 'position' => 100],
        ],
    ]);

    $paint = 'linear-gradient(90deg, #112233 0%, #445566 40%, #778899 100%)';
    expect(PublicAppearance::dividerGradientCss(PublicContentSetting::general()))->toBe($paint);

    $publicBefore = (string) $this->get(route('home'))->assertOk()->getContent();
    expect($publicBefore)->not->toContain('--public-separator-gradient: '.$paint);

    $preview = $this->get('/preview')->assertOk();
    $previewHtml = (string) $preview->getContent();
    expect($previewHtml)->toContain('--public-separator-gradient: '.$paint)
        ->toContain('--public-separator-paint: '.$paint);
    generalAssertPublicStyleUsesNonce($previewHtml, generalPublicStyleNonce($preview), '--public-separator-gradient: '.$paint);

    app(PublicationService::class)->commit($actor, 'Publish menu divider gradient');
    $published = $this->get(route('home'))->assertOk();
    expect((string) $published->getContent())->toContain('--public-separator-gradient: '.$paint);
    generalAssertPublicStyleUsesNonce((string) $published->getContent(), generalPublicStyleNonce($published), '--public-separator-gradient: '.$paint);
});

it('uses the same gradient controls and stops for every global paint including Divider color', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_paint_mode' => 'radial',
        'header_divider_paint_gradient_center_x' => 40,
        'header_divider_paint_gradient_center_y' => 65,
        'header_divider_paint_gradient_stops' => [
            ['color' => '#112233', 'position' => 0],
            ['color' => '#445566', 'position' => 100],
        ],
    ]);

    $settings = PublicContentSetting::general();
    expect(PublicAppearance::dividerGradientCss($settings))
        ->toBe('radial-gradient(circle at 40% 65%, #112233 0%, #445566 100%)');

    Livewire::test(General::class)
        ->assertSet('data.header_divider_paint_mode', 'radial')
        ->assertSet('data.header_divider_paint_gradient_stops', [
            ['color' => '#112233', 'position' => 0],
            ['color' => '#445566', 'position' => 100],
        ])
        ->assertHasNoErrors();
});

it('materializes the Divider Solid fallback only when Solid is explicitly selected', function (): void {
    expect(PublicContentSetting::general()->header_divider_color)->toBeNull();

    Livewire::test(General::class)
        ->assertSet('data.header_divider_paint_mode', 'solid')
        ->assertHasNoErrors();

    expect(PublicContentSetting::general()->header_divider_color)->toBeNull();

    Livewire::test(General::class)
        ->set('data.header_divider_paint_mode', 'gradient')
        ->set('data.header_divider_paint_mode', 'solid')
        ->call('persistSurfaceMode', 'header_divider_paint')
        ->assertHasNoErrors();

    expect(PublicContentSetting::general()->header_divider_color)->toBe(PublicAppearance::DEFAULT_DIVIDER_COLOR);
});

it('uses the single canonical divider Solid color for the shared direct-color update path', function (): void {
    Livewire::test(General::class)
        ->call('persistSurfaceColor', 'header_divider_paint', 'primary', '#AABBCC')
        ->assertHasNoErrors()
        ->assertSet('data.header_divider_color', '#AABBCC')
        ->assertSet('data.header_divider_paint_mode', 'solid');

    $setting = PublicContentSetting::general();
    expect($setting->getAttribute('header_divider_color'))->toBe('#AABBCC')
        ->and($setting->getAttribute('header_divider_paint_mode'))->toBe('solid')
        ->and($setting->getAttribute('header_divider_paint_color'))->toBeNull();
});

it('validates public header settings', function (): void {
    expect(PublicAppearance::headerText('  Neue Edition  '))->toBe('Neue Edition')
        ->and(PublicAppearance::normalizeColor('#ab12ff', 'header_name_color'))->toBe('#AB12FF')
        ->and(PublicAppearance::headerAlignment(null, 'header_name_mobile_alignment'))->toBe('center');

    expect(fn () => PublicAppearance::headerText(''))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerText(str_repeat('X', 101)))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerAlignment('diagonal'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerPosition('overlap'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeTitleMinSize(11))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeTitleSize(65))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeColor('red', 'header_name_color'))->toThrow(ValidationException::class);
});
