<?php

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function customPageComponentAsset(string $suffix): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/custom-page-component-'.$suffix.'.jpg',
        'original_filename' => 'custom-page-component-'.$suffix.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'custom-page-component-'.$suffix),
        'state' => 'available',
        'alt_text' => 'Custom page component '.$suffix,
        'width' => 2,
        'height' => 2,
    ]);
}

it('persists ordered Custom Page components across add remove and reorder changes', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Component page', 'component-persistence-page');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('order');

    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => false],
        ['type' => 'divider'],
        ['type' => 'contact', 'show_email' => true, 'show_form' => true, 'social_platforms' => [], 'form_state' => 'enabled'],
        ['type' => 'text', 'title' => 'Statement', 'body' => 'Persistent text'],
        ['type' => 'list', 'title' => 'Links', 'items' => [['visible' => true, 'title' => 'Example', 'url' => 'https://example.com']]],
    ]]);

    expect(array_column($settings->fresh()->components(), 'type'))->toBe(['image', 'divider', 'contact', 'text', 'list']);

    $components = $settings->fresh()->components();
    $settings->update(['blocks' => [$components[2], $components[0], $components[1], $components[4]]]);

    expect(array_column($settings->fresh()->components(), 'type'))->toBe(['contact', 'image', 'divider', 'list']);
});

it('defaults Contact Form presentation to enabled when child state is absent', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Contact contract', 'contact-component-page');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();

    $settings->update(['blocks' => [[
        'type' => 'contact',
        'children' => [['type' => 'contact_form', 'published' => true]],
    ]]]);

    $block = $settings->fresh()->components()[0];
    expect($settings->fresh()->contactChildren($block)[0]['form_state'])->toBe('enabled');
});

it('accepts external HTTP image sources in Custom Page rich text without fetching them', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('External image contract', 'external-image-component-page');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $body = "![](https://images.example.com/work.jpg)\n\n![](http://images.example.com/archive.jpg)";

    $settings->update(['blocks' => [['type' => 'text', 'body' => $body]]]);
    $settings->fresh()->assertReadyForPublic();

    $rendered = (string) app(SafeRichTextRenderer::class)->render($body);
    expect($rendered)->toContain('src="https://images.example.com/work.jpg"')
        ->and($rendered)->toContain('src="http://images.example.com/archive.jpg"');
});


it('keeps decorative layout semantic and undated CV entries without inventing dates', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('About artist', 'about-artist-components');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('decorative');

    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => true],
        ['type' => 'list', 'title' => 'Curriculum Vitae', 'items' => [
            ['title' => 'Born in Hamburg', 'context' => '1.6.1989'],
            ['title' => 'Studies at Berlin University of the Arts', 'context' => '2010–2021'],
            ['title' => 'Lives and works in Hamburg', 'context' => null],
        ]],
    ]]);

    $blocks = $settings->fresh()->components();
    expect($blocks[0]['image_decorative'])->toBeTrue()
        ->and(CustomPageSetting::listItemContext($blocks[1]['items'][2]))->toBeNull()
        ->and($blocks[1]['items'][2]['title'])->toBe('Lives and works in Hamburg');

    $view = file_get_contents(resource_path('views/pages/partials/component-sequence.blade.php'));
    expect($view)->toContain('custom-page__image--wrapped')
        ->toContain('pages.partials.component-sequence')
        ->toContain('Legal Disclaimer');

    $styles = file_get_contents(resource_path('css/custom-pages.css'));
    expect($styles)->toContain('float: left')
        ->toContain('.custom-page__image-group')
        ->not->toContain('grid-template-columns: subgrid');
});

it('stores ordered decorative Image children and rejects incompatible nested components', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Image composition', 'image-composition');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('nested');
    $children = [
        ['type' => 'list', 'title' => 'CV', 'items' => [
            ['title' => 'Born in Hamburg', 'context' => '1989'],
            ['title' => 'Lives and works in Hamburg', 'context' => null],
        ]],
        ['type' => 'text', 'title' => 'Practice', 'body' => 'My work'],
        ['type' => 'contact', 'title' => 'Contact', 'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => []],
        ]],
    ];
    $settings->update(['blocks' => [[
        'type' => 'image', 'image_decorative' => true,
        'media_asset_id' => $asset->getKey(), 'children' => $children,
    ]]]);
    $saved = $settings->fresh()->components()[0];
    expect(array_column($saved['children'], 'type'))->toBe(['list', 'text', 'contact'])
        ->and(CustomPageSetting::listItemContext($saved['children'][0]['items'][1]))->toBeNull();
    $settings->fresh()->assertReadyForPublic();

    foreach ([
        [['type' => 'image', 'media_asset_id' => $asset->getKey()]],
        [['type' => 'divider', 'variant' => 'thin']],
        [['type' => 'list', 'media_asset_id' => $asset->getKey(), 'items' => []]],
        [['type' => 'contact', 'children' => [['type' => 'contact_form', 'published' => true]]]],
    ] as $invalid) {
        expect(fn () => $settings->update(['blocks' => [[
            'type' => 'image', 'media_asset_id' => $asset->getKey(),
            'image_decorative' => true, 'children' => $invalid,
        ]]]))->toThrow(\Illuminate\Validation\ValidationException::class);
    }
    expect(fn () => $settings->update(['blocks' => [[
        'type' => 'image', 'media_asset_id' => $asset->getKey(),
        'image_decorative' => false, 'children' => $children,
    ]]]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('moves an existing CV list into an Image atomically without losing its entries', function (): void {
    $settings = CustomPageSetting::query()->where(
        'site_section_id',
        app(SiteSectionEditorialService::class)->createCustomPage('Move CV', 'move-existing-cv')->getKey(),
    )->firstOrFail();
    $asset = customPageComponentAsset('cv-move');
    $settings->update(['blocks' => [
        ['type' => 'image', 'image_decorative' => true, 'media_asset_id' => $asset->getKey()],
        ['type' => 'list', 'title' => 'Curriculum Vitae', 'items' => [
            ['title' => 'Born in Hamburg', 'context' => '1989'],
            ['title' => 'Lives and works in Hamburg', 'context' => null],
        ]],
    ]]);
    $original = $settings->fresh()->components();
    $changed = app(\App\Domain\Content\CustomPageEditorialService::class)->updateImageAndNest(
        $settings, 0, $original[0], 1,
    );
    expect($changed)->toBeTrue();
    $after = $settings->fresh()->components();
    expect($after)->toHaveCount(1)
        ->and($after[0]['children'][0])->toBe($original[1]);
});


it('preserves the existing disclaimer component while rendering separately authored legal sections', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Artist legal', 'artist-legal');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $settings->update(['blocks' => [['type' => 'legal_disclaimer', 'published' => true]]]);

    expect($settings->fresh()->components()[0]['type'])->toBe('legal_disclaimer');

    $general = \App\Models\PublicContentSetting::general();
    $general->update([
        'legal_imprint' => "Artist\nStreet 3",
        'legal_privacy' => 'Data processing',
        'legal_usage_rights' => 'No AI training',
        'legal_disclaimer' => 'Archived external links notice',
    ]);

    $sections = \App\Domain\Content\PublicLegalContent::sections($general);
    expect(array_column($sections, 'slug'))->toBe([
        'imprint', 'privacy', 'copyright-ai', 'external-links',
    ]);

    $general->update(['legal_privacy' => null]);
    expect(array_column(\App\Domain\Content\PublicLegalContent::sections($general), 'slug'))
        ->toBe(['imprint', 'copyright-ai', 'external-links']);
});

it('renders legal copy as escaped disclosures and omits unpublished or blank content', function (): void {
    $generalSettings = new \App\Models\PublicContentSetting;
    $generalSettings->setRawAttributes([
        'legal_imprint' => "Line A\nLine B",
        'legal_privacy' => '<script>alert(1)</script>',
        'legal_usage_rights' => null,
        'legal_disclaimer' => 'External notes',
    ], true);
    $blocks = [['type' => 'legal_disclaimer', 'published' => true]];
    $assets = collect();
    $media = null;
    $label = 'About Artist';
    $isHome = false;
    $isPreview = false;

    $html = \Illuminate\Support\Facades\Blade::render(
        "@include('pages.partials.component-sequence')",
        compact('generalSettings', 'blocks', 'assets', 'media', 'label', 'isHome', 'isPreview'),
    );

    expect($html)->toContain('>Legal Disclaimer</h2>')
        ->toContain('id="legal-imprint"')
        ->toContain('id="legal-privacy"')
        ->toContain('id="legal-external-links"')
        ->not->toContain('id="legal-copyright-ai"')
        ->toContain("Line A\nLine B")
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('<details');

    $blocks = [['type' => 'legal_disclaimer', 'published' => false]];
    $hidden = \Illuminate\Support\Facades\Blade::render(
        "@include('pages.partials.component-sequence')",
        compact('generalSettings', 'blocks', 'assets', 'media', 'label', 'isHome', 'isPreview'),
    );
    expect($hidden)->not->toContain('legal-imprint');

    $blocks = [['type' => 'legal_disclaimer', 'published' => true]];
    foreach (['legal_imprint', 'legal_privacy', 'legal_usage_rights', 'legal_disclaimer'] as $field) {
        $generalSettings->setAttribute($field, null);
    }
    $empty = \Illuminate\Support\Facades\Blade::render(
        "@include('pages.partials.component-sequence')",
        compact('generalSettings', 'blocks', 'assets', 'media', 'label', 'isHome', 'isPreview'),
    );
    expect($empty)->not->toContain('custom-page__legal-sections');
});

it('nests and releases a CV list without data loss or implicit sibling changes', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Drag content', 'drag-content');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('drag-test');
    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => true],
        ['type' => 'text', 'title' => 'Before', 'body' => 'Before'],
        ['type' => 'list', 'title' => 'CV', 'items' => [['title' => 'Undated', 'context' => null]]],
        ['type' => 'text', 'title' => 'After', 'body' => 'After'],
    ]]);
    $service = app(\App\Domain\Content\CustomPageEditorialService::class);
    expect($service->nestBlock($settings, 2, 'list', 0))->toBeTrue();
    $nested = $settings->fresh()->components();
    expect(array_column($nested, 'type'))->toBe(['image','text','text'])
        ->and($nested[0]['children'][0]['items'][0]['context'])->toBeNull()
        ->and($nested[1]['title'])->toBe('Before');

    expect($service->unnestImageChild($settings, 0, 0))->toBeTrue();
    $restored = $settings->fresh()->components();
    expect(array_column($restored, 'type'))->toBe(['image','list','text','text'])
        ->and($restored[1]['items'][0]['title'])->toBe('Undated');
});

it('sorts Image children only within their owner while preserving publication states', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Image child sort', 'image-child-sort');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('image-child-sort');
    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => true, 'children' => [
            ['type' => 'text', 'published' => true, 'title' => 'First', 'body' => 'A'],
            ['type' => 'text', 'published' => false, 'title' => 'Second', 'body' => 'B'],
        ]],
        ['type' => 'text', 'title' => 'Outside', 'body' => 'C'],
    ]]);
    $editor = app(\App\Domain\Content\CustomPageEditorialService::class);
    expect($editor->sortImageChild($settings, 0, 1, 0))->toBeTrue();
    $blocks = $settings->fresh()->components();
    expect(array_column($blocks[0]['children'], 'title'))->toBe(['Second', 'First'])
        ->and($blocks[0]['children'][0]['published'])->toBeFalse()
        ->and($blocks[1]['title'])->toBe('Outside');
});

it('moves the edited CV content into its chosen Parent as a single operation', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('CV placement', 'cv-placement');
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = customPageComponentAsset('cv-placement');
    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => true],
        ['type' => 'list', 'title' => 'CV', 'items' => [['title' => 'Born in Hamburg', 'context' => '1989']]],
    ]]);
    $updated = $settings->fresh()->components()[1];
    $updated['items'][0]['title'] = 'Born in Hamburg, Germany';
    expect(app(\App\Domain\Content\CustomPageEditorialService::class)->updateAndNestBlock(
        $settings, 1, 'list', $updated, 0,
    ))->toBeTrue();
    $blocks = $settings->fresh()->components();
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['children'][0]['items'][0]['title'])->toBe('Born in Hamburg, Germany')
        ->and($blocks[0]['children'][0]['items'][0]['context'])->toBe('1989');
});
