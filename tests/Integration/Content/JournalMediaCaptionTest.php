<?php

use App\Domain\Content\JournalMediaRenderer;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;

function journalImageUsage(): array
{
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/blog-caption.jpg',
        'original_filename' => 'blog-caption.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 10,
        'sha256' => hash('sha256', 'caption-original'),
        'state' => 'available',
        'alt_text' => 'An authored image',
        'credit' => 'Photograph by Studio',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_INHERIT,
    ]);
    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/blog-caption.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 10,
        'sha256' => hash('sha256', 'caption-variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    $usage = new JournalEntryMedia;
    $usage->setRelation('mediaAsset', $asset);
    return [$asset, $usage];
}

it('keeps authored image credits visible when Blog copyright captions are suppressed', function (): void {
    PublicContentSetting::general()->update([
        'default_media_copyright_notice' => 'Inherited studio copyright',
    ]);

    [$asset, $usage] = journalImageUsage();
    $renderer = app(JournalMediaRenderer::class);

    $blogCaption = $renderer->render($usage, includeInheritedCopyright: false, includeCopyrightNotice: false)->toHtml();
    expect($blogCaption)->toContain('Photograph by Studio')
        ->not->toContain('Inherited studio copyright');

    $regularCaption = $renderer->render($usage)->toHtml();
    expect($regularCaption)->toContain('Photograph by Studio')
        ->toContain('Inherited studio copyright');

    $asset->update([
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
        'copyright_notice' => 'Artwork-specific copyright',
    ]);

    $overrideCaption = $renderer->render($usage, includeInheritedCopyright: false)->toHtml();
    expect($overrideCaption)->toContain('Photograph by Studio')
        ->toContain('Artwork-specific copyright')
        ->not->toContain('Inherited studio copyright');

    // A Blog-only copyright suppression also hides explicit overrides,
    // but never deletes the separate authored credit.
    $hiddenCopyright = $renderer->render($usage, includeInheritedCopyright: false, includeCopyrightNotice: false)->toHtml();
    expect($hiddenCopyright)->toContain('Photograph by Studio')
        ->not->toContain('Artwork-specific copyright');
});

it('enables the Journal image viewer for opted-in media and Rich Text', function (): void {
    [$asset, $usage] = journalImageUsage();
    $renderer = app(JournalMediaRenderer::class);

    $plain = $renderer->render($usage)->toHtml();
    $interactive = $renderer->render($usage, enableViewer: true)->toHtml();
    expect($plain)->not->toContain('data-artwork-viewer-trigger')
        ->and($interactive)->toContain('data-artwork-viewer-trigger')
        ->toContain('data-viewer-scope="content"')
        ->toContain('data-viewer-kind="image"')
        ->toContain('aria-label="Enlarge image: An authored image"');

    PublicContentSetting::general()->update(['favicon_media_asset_id' => $asset->getKey()]);
    $body = '<p>Studio notes</p><p><img data-id="'.$asset->getKey().'" alt="Detail view"></p>';
    $rich = $renderer->renderRichText($body)->toHtml();
    expect($rich)->toContain('Studio notes')
        ->toContain('data-artwork-viewer-trigger')
        ->toContain('data-viewer-scope="content"')
        ->toContain('data-viewer-alt="Detail view"');
});
