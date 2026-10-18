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
        ->and(PublicAppearance::headerCaps(null))->toBe('small-caps')
        ->and(PublicAppearance::headerCaps('normal'))->toBe('normal')
        ->and(PublicAppearance::headerDivider(null))->toBe('fine')
        ->and(PublicAppearance::headerDivider('full'))->toBe('full')
        ->and(PublicAppearance::headerDivider('full_gap'))->toBe('full_gap')
        ->and(PublicAppearance::headerDivider('separate'))->toBe('separate')
        ->and(PublicAppearance::normalizeTitleSize(null))->toBe(25)
        ->and(PublicAppearance::normalizeTitleSize('46'))->toBe(46)
        ->and(PublicAppearance::normalizeTitleMinSize(null))->toBe(18)
        ->and(PublicAppearance::normalizeTitleMinSize(14))->toBe(14)
        ->and(PublicAppearance::normalizeMode('transparent', 'page_background_mode'))->toBe('transparent')
        ->and(PublicAppearance::normalizeColor('#ab12ff', 'header_name_color'))->toBe('#AB12FF');

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
    expect($preview)->toContain('site-header--name-outside')
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
    expect($public)->toContain('site-header--name-outside')
        ->toContain('site-header--divider-full_gap')
        ->toContain('site-content--transparent')
        ->toContain('data-public-layout-title-color="#AABBCC"');
});

it('keeps navigation-only Group Nodes distinct from Galleries', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $service = app(SiteSectionEditorialService::class);
    $group = $service->createNavigationGroup('Prints');
    expect($group->getAttribute('slug'))->toBeNull()
        ->and($group->nodeType()->hasPublicPage())->toBeFalse();
});
