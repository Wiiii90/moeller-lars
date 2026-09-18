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
