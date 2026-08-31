<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\JournalEntryMedia;
use Illuminate\Support\HtmlString;

final class JournalMediaRenderer
{
    public function __construct(private readonly PublicMedia $media) {}

    public function render(JournalEntryMedia $usage, string $class = 'journal-entry-media', bool $priority = false): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        $variant = $this->media->thumbnailVariantForAsset($asset);
        $alt = $this->media->altTextForAsset($asset);
        $width = (int) ($variant->getAttribute('width') ?? 0);
        $height = (int) ($variant->getAttribute('height') ?? 0);
        $credit = trim((string) ($asset->getAttribute('credit') ?? ''));
        $copyright = trim((string) ($asset->effectiveCopyrightNotice() ?? ''));
        $caption = trim(implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== '')));

        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $figure = '<figure class="'.e($class).'">'
            .'<img src="'.e($this->media->variantUrl($variant)).'" alt="'.e($alt).'"'.$dimensions
            .' loading="'.($priority ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($priority ? 'high' : 'auto').'">'
            .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')
            .'</figure>';

        return new HtmlString($figure);
    }
}
