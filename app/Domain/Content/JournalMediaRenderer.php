<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Domain\Media\MediaTypePolicy;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;
use LogicException;

final class JournalMediaRenderer
{
    public function __construct(private readonly PublicMedia $media) {}

    public function inheritsDefaultNotice(JournalEntryMedia $usage): bool
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->mediaAsset;

        return $asset instanceof MediaAsset
            && ! in_array((string) $asset->getAttribute('copyright_notice_mode'), [
                MediaAsset::COPYRIGHT_OVERRIDE,
                MediaAsset::COPYRIGHT_NONE,
            ], true);
    }

    public function render(JournalEntryMedia $usage, string $class = 'journal-entry-media', bool $priority = false, bool $includeInheritedCopyright = true, bool $includeCopyrightNotice = true, bool $showCaption = true): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Journal media usage requires a media asset.');
        }

        $isVideo = MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'));
        $variant = $isVideo ? null : $this->media->thumbnailVariantForAsset($asset);
        $alt = $this->media->altTextForAsset($asset);
        $width = (int) ($variant?->getAttribute('width') ?? 0);
        $height = (int) ($variant?->getAttribute('height') ?? 0);
        $credit = trim((string) ($asset->getAttribute('credit') ?? ''));
        // Blog presentation can hide all visual copyright notices while
        // preserving separately authored credits and the stored copyright data.
        // Other Journal surfaces retain their own inheritance/override policy.
        $copyright = '';
        if ($includeCopyrightNotice && ($includeInheritedCopyright || ! $this->inheritsDefaultNotice($usage))) {
            $copyright = trim((string) ($asset->effectiveCopyrightNotice() ?? ''));
        }
        // Public Journal presentations may omit visible figure captions.
        // Asset credit, copyright inheritance and accessible ALT remain owned
        // by their existing canonical media metadata, not this display flag.
        $caption = trim(implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== '')));

        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $element = $isVideo
            ? '<video src="'.e($this->media->originalUrlForAsset($asset)).'" aria-label="'.e($alt).'"'
                .' controls muted loop playsinline preload="metadata" data-public-loop-video></video>'
            : '<img src="'.e($this->media->variantUrl($variant)).'" alt="'.e($alt).'"'.$dimensions
                .' loading="'.($priority ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($priority ? 'high' : 'auto').'">';
        $figure = '<figure class="'.e($class).'">'
            .$element
            .($showCaption && $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')
            .'</figure>';

        return new HtmlString($figure);
    }
}
