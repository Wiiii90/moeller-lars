<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Admin/Gallery/GalleryFixtures.php';

it('validates editable name positions, lettering, colors and sizes', function (): void {
    expect(PublicAppearance::headerPosition(null))->toBe('inside')
        ->and(PublicAppearance::headerPosition('outside'))->toBe('outside')
        ->and(PublicAppearance::headerPosition('below'))->toBe('below')
        ->and(PublicAppearance::headerPosition('above'))->toBe('above')
        ->and(PublicAppearance::headerCaps(null))->toBe('small-caps')
        ->and(PublicAppearance::headerCaps('normal'))->toBe('normal')
        ->and(PublicAppearance::menuType(null))->toBe('cyclic_inline')
        ->and(PublicAppearance::menuType('cyclic_inline'))->toBe('cyclic_inline')
        ->and(PublicAppearance::headerDivider(null))->toBe('fine')
        ->and(PublicAppearance::headerDivider('full'))->toBe('full')
        ->and(PublicAppearance::headerDivider('menu'))->toBe('menu')
        ->and(PublicAppearance::headerDivider('full_gap'))->toBe('full_gap')
        ->and(PublicAppearance::headerDivider('separate'))->toBe('separate')
        ->and(PublicAppearance::normalizeTitleSize(null))->toBe(25)
        ->and(PublicAppearance::normalizeTitleSize('46'))->toBe(46)
        ->and(PublicAppearance::normalizeTitleMinSize(null))->toBe(18)
        ->and(PublicAppearance::normalizeTitleMinSize(14))->toBe(14)
        ->and(PublicAppearance::normalizeMode('transparent', 'page_background_mode'))->toBe('transparent')
        ->and(PublicAppearance::normalizeColor('#ab12ff', 'header_name_color'))->toBe('#AB12FF');

    expect(PublicAppearance::frameStyle(null))->toBe('layered')
        ->and(PublicAppearance::frameStyle('plain'))->toBe('plain')
        ->and(PublicAppearance::frameStyle('continuous'))->toBe('continuous')
        ->and(PublicAppearance::frameStyle('contours'))->toBe('contours')
        ->and(PublicAppearance::frameStyle('backing'))->toBe('backing')
        ->and(PublicAppearance::presentedHeaderAlignment('center', 'inside'))->toBe('left')
        ->and(PublicAppearance::presentedHeaderAlignment('center', 'above'))->toBe('center')
        ->and(array_keys(PublicAppearance::headerAlignmentOptions('inside')))->toBe(['left', 'right'])
        ->and(array_keys(PublicAppearance::headerAlignmentOptions('above')))->toBe(['left', 'center', 'right']);
    expect(fn () => PublicAppearance::frameStyle('faux-paper'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::menuType('dropdown'))->toThrow(ValidationException::class);
    expect(PublicAppearance::headerText(null))->toBe('Lars Möller')
        ->and(PublicAppearance::headerText(' Neue Edition '))->toBe('Neue Edition')
        ->and(PublicAppearance::headerAlignment(null))->toBe('left')
        ->and(PublicAppearance::headerAlignment(null, 'header_name_mobile_alignment'))->toBe('center')
        ->and(PublicAppearance::headerAlignment('right'))->toBe('right');
    expect(fn () => PublicAppearance::headerText(''))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerText(str_repeat('X', 101)))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerAlignment('diagonal'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerPosition('overlap'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeMode('transparent', 'menu_background_mode'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeTitleMinSize(11))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerCaps('uppercase'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerDivider('joined'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeTitleSize(65))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeTitleSize('21.5'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::normalizeColor('red', 'header_name_color'))->toThrow(ValidationException::class);
});

it('publishes editable name presentation but never redirects a gallery parent to its child', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $parent = galleryFixtureCategory();
    $child = galleryFixtureCategory();

    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->getKey())->firstOrFail();
    $childSection = SiteSection::query()->where('artwork_category_id', $child->getKey())->firstOrFail();

    app(SiteSectionEditorialService::class)->updatePlacement($childSection, 'published', (int) $parentSection->getKey());
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'frame_style' => 'plain',
        'header_name_visible' => true,
        'header_name_text' => 'Atelier Neue',
        'header_name_alignment' => 'right',
        'header_name_mobile_alignment' => 'left',
        'header_name_position' => 'outside',
        'header_name_caps' => 'normal',
        'header_name_color' => '#aabbcc',
        'header_name_size' => 46,
        'header_divider_mode' => 'full_gap',
        'header_divider_color' => '#123456',
        'header_name_auto_scale' => false,
        'header_name_min_size' => 14,
        'page_background_mode' => PublicAppearance::MODE_TRANSPARENT,
    ]);

    $preview = $this->get('/preview/'.$parent->slug)->assertOk()->getContent();
    expect($preview)->toContain('public-site--frame-plain')
        ->toContain('data-public-layout-frame-style="plain"')
        ->toContain('site-header--name-outside')
        ->toContain('site-header--title-align-right')
        ->toContain('site-header--mobile-title-align-left')
        ->toContain('aria-label="Atelier Neue — Home"')
        ->toContain('>Atelier Neue</a>')
        ->toContain('site-header--caps-normal')
        ->toContain('site-header--divider-full_gap')
        ->not->toContain('site-header--title-fluid')
        ->toContain('site-content--transparent')
        ->toContain('data-public-layout-title-min-size="14px"')
        ->toContain('data-public-layout-content-background="transparent"')
        ->toContain('data-public-layout-divider-color="#123456"')
        ->toContain('data-public-layout-title-color="#AABBCC"')
        ->toContain('data-public-layout-title-size="46px"');

    app(PublicationService::class)->commit($actor, 'Publish artist name settings and nested Gallery');
    $public = $this->get('/'.$parent->slug)->assertOk()->getContent();
    expect($public)->toContain('public-site--frame-plain')
        ->toContain('site-header--name-outside')
        ->toContain('>Atelier Neue</a>')
        ->toContain('site-header--divider-full_gap')
        ->toContain('site-content--transparent')
        ->toContain('data-public-layout-title-color="#AABBCC"');
});

it('keeps one connected paper frame for the above-menu title without a divider', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $category = galleryFixtureCategory();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'frame_style' => 'layered',
        'header_name_visible' => true,
        'header_name_position' => 'above',
        'header_name_alignment' => 'right',
        'header_divider_mode' => 'fine',
    ]);

    $preview = $this->get('/preview/'.$category->slug)->assertOk()->getContent();
    expect($preview)->toContain('site-header--name-above')
        ->toContain('site-header--title-align-right')
        ->toContain('public-paper-frame--connected')
        ->toContain('public-paper-decoration')
        ->toContain('site-header--divider-fine');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'frame_style' => 'continuous',
        'header_divider_mode' => 'menu',
    ]);
    $menu = $this->get('/preview/'.$category->slug)->assertOk()->getContent();
    expect($menu)->toContain('public-site--frame-continuous')
        ->toContain('public-paper-frame--compatible')
        ->toContain('public-paper-frame--connected')
        ->toContain('site-header--divider-menu');

    foreach (['separate', 'full_gap'] as $mode) {
        app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
            'header_divider_mode' => $mode,
        ]);
        $separate = $this->get('/preview/'.$category->slug)->assertOk()->getContent();
        expect($separate)->toContain('public-paper-frame--compatible')
            ->not->toContain('public-paper-frame--connected')
            ->toContain('site-header--divider-'.$mode);
    }

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_divider_mode' => 'menu',
    ]);

    app(PublicationService::class)->commit($actor, 'Publish paper and divider settings');
    $public = $this->get('/'.$category->slug)->assertOk()->getContent();
    expect($public)->toContain('public-site--frame-continuous')
        ->toContain('site-header--divider-menu');
});

it('can hide the artist title without removing the public navigation', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $category = galleryFixtureCategory();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_name_visible' => false,
        'header_name_position' => 'inside',
        'header_name_text' => 'Private working title',
    ]);

    $preview = $this->get('/preview/'.$category->slug)->assertOk()->getContent();
    expect($preview)->toContain('site-header--title-hidden')
        ->not->toContain('class="site-title"')
        ->not->toContain('Private working title')
        ->toContain('data-site-navigation');

    app(PublicationService::class)->commit($actor, 'Hide public artist title');
    $public = $this->get('/'.$category->slug)->assertOk()->getContent();
    expect($public)->toContain('site-header--title-hidden')
        ->not->toContain('class="site-title"')
        ->toContain('data-site-navigation');
});

it('keeps navigation-only Group Nodes distinct from Galleries', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $service = app(SiteSectionEditorialService::class);
    $group = $service->createNavigationGroup('Prints');
    expect($group->getAttribute('slug'))->toBeNull()
        ->and($group->nodeType()->hasPublicPage())->toBeFalse();
});
