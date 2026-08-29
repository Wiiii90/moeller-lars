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

it('renders the exact six General metrics from persisted settings and audit events', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'public_email' => 'public@example.invalid',
        'show_public_email' => true,
        'contact_recipient_email' => 'delivery@example.invalid',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram', 'visible' => true],
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
        ->assertSee($settings->updated_at->format('Y-m-d H:i'))
        ->assertSee('Changes · 30d')
        ->assertSee((string) $changes)
        ->assertSee('Public email')
        ->assertSee('Contact delivery')
        ->assertSee('Social profiles')
        ->assertSee('Legal')
        ->assertDontSee('Appearance')
        ->assertDontSee('Save changes');

    $metricSource = file_get_contents(resource_path('views/filament/schemas/components/general-status-metrics.blade.php'));
    expect(substr_count((string) $metricSource, '<x-admin.metric '))->toBe(6)
        ->and($metricSource)->toContain('label="Last changed"')
        ->and($metricSource)->toContain('label="Changes · 30d"')
        ->and($metricSource)->toContain('label="Public email"')
        ->and($metricSource)->toContain('label="Contact delivery"')
        ->and($metricSource)->toContain('label="Social profiles"')
        ->and($metricSource)->toContain('label="Legal"')
        ->and($metricSource)->toContain("->where('entity_id', (int) \$settings->getKey())")
        ->and($metricSource)->toContain("->where('occurred_at', '>=', now()->subDays(30))")
        ->and($metricSource)->not->toContain('admin-metrics--open-bottom');
});

it('keeps the General browser contract flat and reuses shared control table and pager grammar', function (): void {
    $pageSource = file_get_contents(app_path('Filament/Pages/General.php'));
    $adminFormSource = file_get_contents(app_path('Filament/Support/AdminForm.php'));
    $colorSource = file_get_contents(app_path('Filament/Support/AdminColorControl.php'));
    $helpSource = file_get_contents(app_path('Filament/Support/AdminHelp.php'));
    $helpViewSource = file_get_contents(resource_path('views/components/admin/help.blade.php'));
    $viewSource = file_get_contents(resource_path('views/filament/pages/general.blade.php'));
    $separatorSource = file_get_contents(resource_path('views/filament/schemas/components/general-separator.blade.php'));
    $socialSource = file_get_contents(resource_path('views/filament/schemas/components/general-social-links.blade.php'));
    $metricSource = file_get_contents(resource_path('views/filament/schemas/components/general-status-metrics.blade.php'));
    $booleanSource = file_get_contents(app_path('Filament/Support/AdminBooleanControl.php'));
    $controlOrderMatches = preg_match(
        '/<x-slot:search>.*?<span class="admin-field__label">Visibility<\/span>.*?<x-slot:reset>.*?<span class="admin-control-group__label">Social links<\/span>.*?<x-slot:selection>/s',
        $socialSource,
    );
    $tableOrderMatches = preg_match(
        '/admin-table__selection.*?>Drag<\/th>.*?>Position<\/th>.*?>Platform<\/th>.*?>Profile URL<\/th>.*?>Visibility<\/th>.*?admin-table__actions">Actions<\/th>/s',
        $socialSource,
    );
    $generalOrderMatches = preg_match(
        "/'Site icon'.*?->label\('Background'\).*?general-separator'.*?general-social-links'.*?general-separator'.*?TextInput::make\('public_email'\).*?AdminBooleanControl::make\('show_public_email'.*?TextInput::make\('contact_recipient_email'\).*?general-separator'.*?TextInput::make\('default_media_copyright_notice'\).*?Textarea::make\('legal_disclaimer'\)/s",
        $pageSource,
    );

    expect($controlOrderMatches)->toBe(1)
        ->and($tableOrderMatches)->toBe(1)
        ->and($generalOrderMatches)->toBe(1)
        ->and($pageSource)->not->toContain('AdminForm::section(')
        ->and($pageSource)->not->toContain("Text::make('Appearance')")
        ->and($pageSource)->not->toContain("section('Appearance'")
        ->and($pageSource)->not->toContain("Text::make('Social links')")
        ->and($pageSource)->not->toContain("Text::make('Contact')")
        ->and($pageSource)->not->toContain("Text::make('Legal')")
        ->and(substr_count((string) $pageSource, "View::make('filament.schemas.components.general-separator')"))->toBe(3)
        ->and($pageSource)->toContain('MediaAssetSelect::make')
        ->and($pageSource)->toContain("'Site icon'")
        ->and($pageSource)->not->toContain("Text::make('Site icon')")
        ->and($pageSource)->not->toContain('Replace from Media Files')
        ->and($pageSource)->not->toContain('favicon-preview')
        ->and($pageSource)->not->toContain('favicon-actions')
        ->and($pageSource)->toContain("Select::make('background_mode')")
        ->and($pageSource)->toContain("PublicAppearance::MODE_SOLID => 'Solid'")
        ->and($pageSource)->toContain("PublicAppearance::MODE_GRADIENT => 'Gradient'")
        ->and($pageSource)->not->toContain("Radio::make('background_mode')")
        ->and($pageSource)->toContain("->label('Background')")
        ->and($pageSource)->toContain("->visible(fn (callable \$get): bool => \$get('background_mode') === PublicAppearance::MODE_GRADIENT)")
        ->and($pageSource)->toContain('AdminColorControl::make')
        ->and($pageSource)->toContain('AdminHelp::make')
        ->and($pageSource)->not->toContain('Repeater::')
        ->and($pageSource)->not->toContain('Toggle::')
        ->and($pageSource)->not->toContain('settingsSection(')
        ->and($adminFormSource)->toContain('Filament\\Schemas\\Components\\Section')
        ->and($adminFormSource)->not->toContain('Fieldset')
        ->and($colorSource)->toContain('ColorPicker::make')
        ->and($helpSource)->toContain("view('components.admin.help'")
        ->and($helpViewSource)->toContain('x-on:mouseenter')
        ->and($helpViewSource)->toContain('x-on:focusin')
        ->and($helpViewSource)->toContain('x-on:click.stop')
        ->and($viewSource)->not->toContain('general-workspace__sheet')
        ->and($separatorSource)->toContain('border-[var(--admin-line)]')
        ->and($metricSource)->not->toContain('admin-metrics--open-bottom')
        ->and($socialSource)->toContain('<x-admin.controls')
        ->and($socialSource)->toContain('<x-slot:search>')
        ->and($socialSource)->toContain('wire:model.live.debounce.300ms="socialSearch"')
        ->and($socialSource)->toContain('wire:model.live="socialVisibility"')
        ->and($socialSource)->toContain('<span class="admin-field__label">Visibility</span>')
        ->and($socialSource)->toContain('<span class="admin-control-group__label">Filter</span>')
        ->and($socialSource)->toContain('<span class="admin-control-group__label">Social links</span>')
        ->and($socialSource)->toContain('<span class="admin-control-group__label">Selection</span>')
        ->and($socialSource)->toContain('<x-admin.table class="admin-table--data"')
        ->and($socialSource)->toContain('>Drag</th>')
        ->and($socialSource)->toContain('>Position</th>')
        ->and($socialSource)->toContain('wire:sort="sortSocialLink"')
        ->and($socialSource)->toContain('AdminBooleanControl::options')
        ->and($socialSource)->toContain('class="admin-form-control admin-boolean-control"')
        ->and($socialSource)->toContain('wire:blur="updateSocialLink')
        ->and($socialSource)->toContain('>Up</button>')
        ->and($socialSource)->toContain('>Down</button>')
        ->and($socialSource)->toContain('>Delete</button>')
        ->and($socialSource)->toContain('Bulk Delete')
        ->and($socialSource)->toContain('class="admin-pager"')
        ->and($socialSource)->toContain('wire:model.live.number="socialPageSize"')
        ->and($socialSource)->toContain('<option value="25"')
        ->and($socialSource)->toContain('<option value="50"')
        ->and($socialSource)->toContain('<option value="100"')
        ->and($socialSource)->toContain('>Previous</button>')
        ->and($socialSource)->toContain('>Next</button>')
        ->and($booleanSource)->toContain('->native()')
        ->and($booleanSource)->not->toContain('native(false)');
});

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

it('filters paginates selects and drag-reorders social links by array position', function (): void {
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://example.invalid/alpha', 'visible' => true],
            ['platform' => 'facebook', 'url' => 'https://example.invalid/beta', 'visible' => false],
        ],
    ]);

    $component = Livewire::test(General::class)
        ->assertSet('socialPageSize', 25)
        ->assertSet('socialVisibility', 'any')
        ->assertSet('socialSearch', '')
        ->call('sortSocialLink', 1, 0);

    $fresh = PublicContentSetting::general();
    expect($fresh->getAttribute('social_links')[0]['platform'])->toBe('facebook');

    $component
        ->set('socialSearch', 'alpha');

    expect($component->instance()->socialFilteredCount())->toBe(1)
        ->and($component->instance()->canDragSortSocialLinks())->toBeFalse();

    $component
        ->call('resetSocialFilters')
        ->call('toggleSocialSelection', 0)
        ->call('deleteSelectedSocialLinks');

    expect(PublicContentSetting::general()->getAttribute('social_links'))->toHaveCount(1);
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
    $socialSource = file_get_contents(resource_path('views/filament/schemas/components/general-social-links.blade.php'));

    expect($pageSource)->not->toContain('live(debounce:')
        ->and($pageSource)->not->toContain('debounce(')
        ->and($pageSource)->not->toContain('setTimeout')
        ->and($viewSource)->not->toContain('wire:model.debounce')
        ->and($socialSource)->toContain('wire:blur="updateSocialLink')
        ->and($socialSource)->not->toContain('wire:model.debounce.300ms="data.social_links')
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
