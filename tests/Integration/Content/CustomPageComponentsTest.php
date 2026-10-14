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
    expect($view)->toContain('custom-page__image--decorative')
        ->toContain('custom-page__list-item--with-context')
        ->toContain('Rechtlicher Hinweis');

    $styles = file_get_contents(resource_path('css/custom-pages.css'));
    expect($styles)->toContain('float: left')
        ->toContain('grid-template-columns: subgrid')
        ->toContain('.custom-page__legal');
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
